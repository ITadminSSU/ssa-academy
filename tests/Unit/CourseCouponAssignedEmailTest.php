<?php

use App\Http\Controllers\Course\CourseCouponController;
use App\Http\Requests\CourseCouponRequest;
use App\Models\Course\Course;
use App\Models\Course\CourseCoupon;
use App\Models\User;
use App\Services\Course\CourseCouponService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\PaymentGateways\Models\PaymentHistory;

beforeEach(function () {
    $this->withoutMiddleware();

    foreach (['course_coupon_assigned_emails', 'payment_histories', 'course_coupons', 'courses', 'users'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('role')->nullable();
        $table->string('user_type')->nullable();
        $table->string('candidate_status')->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamps();
    });

    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('slug')->nullable();
        $table->decimal('price', 10, 2)->nullable();
        $table->timestamps();
    });

    Schema::create('course_coupons', function (Blueprint $table) {
        $table->id();
        $table->string('code')->unique();
        $table->string('discount_type')->default('percentage');
        $table->decimal('discount', 10, 2);
        $table->timestamp('valid_from')->nullable();
        $table->timestamp('valid_to')->nullable();
        $table->string('usage_type')->default('unlimited');
        $table->unsignedInteger('usage_limit')->nullable();
        $table->unsignedInteger('used_count')->default(0);
        $table->boolean('is_active')->default(true);
        $table->boolean('show_on_catalog')->default(false);
        $table->string('assigned_email')->nullable();
        $table->unsignedBigInteger('course_id')->nullable();
        $table->unsignedBigInteger('created_by')->nullable();
        $table->timestamps();
    });

    Schema::create('course_coupon_assigned_emails', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('course_coupon_id');
        $table->string('email');
        $table->timestamps();
        $table->unique(['course_coupon_id', 'email']);
    });

    Schema::create('payment_histories', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('coupon')->nullable();
        $table->decimal('amount', 10, 2)->nullable();
        $table->string('purchase_type')->nullable();
        $table->unsignedBigInteger('purchase_id')->nullable();
        $table->json('meta')->nullable();
        $table->timestamps();
    });
});

function couponCourse(): Course
{
    return Course::create([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-estimating-'.uniqid(),
        'price' => 100,
    ]);
}

function couponAdmin(): User
{
    return User::create([
        'name' => 'Admin',
        'email' => 'admin-'.uniqid().'@example.com',
        'password' => 'password',
        'role' => 'admin',
        'user_type' => 'external',
        'email_verified_at' => now(),
    ]);
}

function couponStudent(string $email): User
{
    return User::create([
        'name' => 'Student',
        'email' => $email,
        'password' => 'password',
        'role' => 'student',
        'user_type' => 'external',
        'email_verified_at' => now(),
    ]);
}

function openCoupon(array $overrides = []): CourseCoupon
{
    return CourseCoupon::create(array_merge([
        'code' => 'OPEN20',
        'discount_type' => 'percentage',
        'discount' => 20,
        'is_active' => true,
        'usage_type' => 'unlimited',
    ], $overrides));
}

function submitCouponUpdate(User $admin, CourseCoupon $coupon, array $payload): ?ValidationException
{
    $update = CourseCouponRequest::create(route('course-coupons.update', $coupon), 'PUT', $payload);
    $update->setContainer(app());
    $update->setRedirector(app('redirect'));
    $update->setUserResolver(fn () => $admin);
    $route = new Route(['PUT'], '/coupons/{coupon}', []);
    $parameters = (new ReflectionClass($route))->getProperty('parameters');
    $parameters->setValue($route, ['coupon' => $coupon]);
    $update->setRouteResolver(fn () => $route);

    try {
        $update->validateResolved();
    } catch (ValidationException $exception) {
        return $exception;
    }

    app(CourseCouponController::class)->update($update, $coupon);

    return null;
}

function payableDiscount(?CourseCoupon $coupon, float $subtotal = 100): float
{
    if (! $coupon) {
        return 0.0;
    }

    return app(CourseCouponService::class)->discountForAmount($coupon, $subtotal);
}

it('keeps a blank email open to any student, including coupons already saved', function () {
    $legacy = openCoupon();
    $guest = app(CourseCouponService::class)->resolveCouponForCheckout('', 'open20', null);
    $student = couponStudent('anyone@example.com');
    $signedIn = app(CourseCouponService::class)->resolveCouponForCheckout('', 'OPEN20', $student->id);

    expect($legacy->assigned_email)->toBeNull()
        ->and($guest['coupon']?->id)->toBe($legacy->id)
        ->and($guest['error'])->toBeNull()
        ->and(payableDiscount($guest['coupon']))->toBe(20.0)
        ->and($signedIn['coupon']?->id)->toBe($legacy->id)
        ->and(payableDiscount($signedIn['coupon']))->toBe(20.0);
});

it('accepts only the signed-in account whose email matches after trim and case folding', function () {
    $course = couponCourse();
    $owner = couponStudent('  Student@Example.com  ');
    $other = couponStudent('other@example.com');
    $coupon = openCoupon([
        'code' => 'LOCK10',
        'discount' => 10,
        'course_id' => $course->id,
        'assigned_email' => ' student@example.com ',
        'show_on_catalog' => true,
    ]);

    $service = app(CourseCouponService::class);
    $match = $service->resolveCouponForCheckout((string) $course->id, 'lock10', $owner->id);
    $mismatch = $service->resolveCouponForCheckout((string) $course->id, 'LOCK10', $other->id);
    $guest = $service->getCourseValidCoupon((string) $course->id, 'LOCK10', null);

    expect($match['coupon']?->id)->toBe($coupon->id)
        ->and($match['error'])->toBeNull()
        ->and(payableDiscount($match['coupon']))->toBe(10.0)
        ->and($service->getCourseValidCoupon((string) $course->id, 'LOCK10', $owner->id)?->id)->toBe($coupon->id)
        ->and($mismatch['coupon'])->toBeNull()
        ->and($mismatch['error'])->toBe('This coupon is assigned to a different email.')
        ->and(payableDiscount($mismatch['coupon']))->toBe(0.0)
        ->and($guest)->toBeNull()
        ->and($service->resolveCouponForCheckout((string) $course->id, 'LOCK10', null)['error'])
        ->toBe('Sign in with the email this coupon is assigned to.');
});

it('rejects an email-locked code during coupon verification', function () {
    openCoupon([
        'code' => 'LOCK10',
        'assigned_email' => 'student@example.com',
    ]);
    $owner = couponStudent('Student@Example.com');
    $other = couponStudent('other@example.com');

    $verify = function (?User $user) {
        $request = Request::create('/coupons/verify', 'POST', ['code' => 'LOCK10']);
        $request->setUserResolver(fn () => $user);

        return app(CourseCouponController::class)->verify($request);
    };

    $matched = $verify($owner);
    $rejected = $verify($other);
    $guest = $verify(null);

    expect($matched->getStatusCode())->toBe(200)
        ->and($matched->getData(true)['valid'])->toBeTrue()
        ->and($rejected->getStatusCode())->toBe(404)
        ->and($rejected->getData(true)['message'])->toBe('This coupon is assigned to a different email.')
        ->and($guest->getStatusCode())->toBe(404)
        ->and($guest->getData(true)['message'])->toBe('Sign in with the email this coupon is assigned to.');
});

it('keeps course, date, usage, and one-student limits ahead of the email check', function () {
    $course = couponCourse();
    $otherCourse = couponCourse();
    $owner = couponStudent('student@example.com');
    $service = app(CourseCouponService::class);

    $wrongCourse = openCoupon([
        'code' => 'COURSE1',
        'course_id' => $course->id,
        'assigned_email' => 'student@example.com',
    ]);
    $expired = openCoupon([
        'code' => 'OLD10',
        'assigned_email' => 'student@example.com',
        'valid_to' => now()->subDay(),
    ]);
    $usedUp = openCoupon([
        'code' => 'ONCE',
        'assigned_email' => 'student@example.com',
        'usage_type' => 'limited',
        'usage_limit' => 1,
        'used_count' => 1,
    ]);
    $alreadyUsed = openCoupon([
        'code' => 'MINE',
        'assigned_email' => 'student@example.com',
    ]);
    PaymentHistory::create([
        'user_id' => $owner->id,
        'coupon' => 'MINE',
        'amount' => 80,
        'purchase_type' => Course::class,
        'purchase_id' => $course->id,
    ]);

    expect($service->resolveCouponForCheckout((string) $otherCourse->id, $wrongCourse->code, $owner->id)['error'])
        ->toBe('This coupon is not valid for this course.')
        ->and($service->resolveCouponForCheckout((string) $course->id, $expired->code, $owner->id)['error'])
        ->toBe('This coupon is not valid or has expired.')
        ->and($service->resolveCouponForCheckout((string) $course->id, $usedUp->code, $owner->id)['error'])
        ->toBe('This coupon is not valid or has expired.')
        ->and($service->resolveCouponForCheckout((string) $course->id, $alreadyUsed->code, $owner->id)['error'])
        ->toBe('You have already used this voucher for this course.')
        ->and(payableDiscount($service->resolveCouponForCheckout((string) $course->id, $alreadyUsed->code, $owner->id)['coupon']))
        ->toBe(0.0);
});

it('hides email-locked codes from the catalog card and public suggestions', function () {
    $course = couponCourse();
    $open = openCoupon([
        'code' => 'PUBLIC',
        'course_id' => $course->id,
        'show_on_catalog' => true,
        'discount' => 15,
    ]);
    openCoupon([
        'code' => 'PRIVATE',
        'course_id' => $course->id,
        'show_on_catalog' => true,
        'assigned_email' => 'student@example.com',
        'discount' => 50,
    ]);

    $service = app(CourseCouponService::class);
    $service->appendCatalogPromos($course);
    $promo = $course->getAttribute('catalog_promo');

    expect($service->featuredCatalogCouponFor($course)?->id)->toBe($open->id)
        ->and($service->getCourseValidCoupons((string) $course->id)->pluck('code')->all())->toBe(['PUBLIC'])
        ->and(json_encode($promo))->not->toContain('PRIVATE');
});

it('saves, lists, and clears an assigned email from the coupon form', function () {
    $admin = couponAdmin();
    $course = couponCourse();

    $this->actingAs($admin)->post(route('course-coupons.store'), [
        'code' => 'LOCK10',
        'course_id' => $course->id,
        'discount_type' => 'percentage',
        'discount' => 10,
        'is_active' => true,
        'show_on_catalog' => true,
        'assigned_email' => '  Student@Example.com ',
    ])->assertRedirect(route('course-coupons.index'));

    $coupon = CourseCoupon::query()->where('code', 'LOCK10')->first();

    expect($coupon)->not->toBeNull()
        ->and($coupon->assigned_email)->toBe('student@example.com')
        ->and($coupon->show_on_catalog)->toBeFalse();

    $update = CourseCouponRequest::create(route('course-coupons.update', $coupon), 'PUT', [
        'code' => 'LOCK10',
        'course_id' => $course->id,
        'discount_type' => 'percentage',
        'discount' => 10,
        'is_active' => true,
        'show_on_catalog' => true,
        'assigned_email' => '   ',
    ]);
    $update->setContainer(app());
    $update->setRedirector(app('redirect'));
    $update->setUserResolver(fn () => $admin);
    $route = new Route(['PUT'], '/coupons/{coupon}', []);
    $parameters = (new ReflectionClass($route))->getProperty('parameters');
    $parameters->setValue($route, ['coupon' => $coupon]);
    $update->setRouteResolver(fn () => $route);
    $update->validateResolved();

    app(CourseCouponController::class)->update($update, $coupon);

    expect($coupon->fresh()->assigned_email)->toBeNull()
        ->and($coupon->fresh()->show_on_catalog)->toBeTrue();
});

it('imports a csv without an email column as open codes and does not clear an existing assignment', function () {
    $admin = couponAdmin();
    $locked = openCoupon([
        'code' => 'KEEPME',
        'assigned_email' => 'student@example.com',
        'discount' => 5,
    ]);

    $csv = implode("\n", [
        'code,discount_type,discount,course_id,valid_from,valid_to,is_active',
        'NEWOPEN,percentage,20,,2026-08-19,2026-12-31,true',
        'KEEPME,fixed,15,,2026-08-19,2026-12-31,true',
    ]);

    $this->actingAs($admin)->post(route('course-coupons.import'), [
        'csv_content' => $csv,
        'import_mode' => 'csv',
        'on_duplicate' => 'update',
    ])->assertRedirect(route('course-coupons.index'));

    expect(CourseCoupon::query()->where('code', 'NEWOPEN')->first()?->assigned_email)->toBeNull()
        ->and($locked->fresh()->assigned_email)->toBe('student@example.com')
        ->and((float) $locked->fresh()->discount)->toBe(15.0);
});

it('imports an optional email column, clears a blank cell, and skips an invalid email', function () {
    $admin = couponAdmin();
    $course = couponCourse();
    $named = openCoupon([
        'code' => 'NAMED',
        'course_id' => $course->id,
        'show_on_catalog' => true,
        'discount' => 5,
    ]);
    $clear = openCoupon([
        'code' => 'CLEARME',
        'assigned_email' => 'old@example.com',
    ]);

    $csv = implode("\n", [
        'code,discount_type,discount,email',
        'NAMED,percentage,10,  Person@Example.com ',
        'CLEARME,percentage,10,',
        'BAD,fixed,5,not-an-email',
        'OPENROW,fixed,5,',
    ]);

    $this->actingAs($admin)->post(route('course-coupons.import'), [
        'csv_content' => $csv,
        'import_mode' => 'csv',
        'on_duplicate' => 'update',
    ])->assertRedirect(route('course-coupons.index'))
        ->assertSessionHas('success');

    expect($named->fresh()->assigned_email)->toBe('person@example.com')
        ->and($named->fresh()->checkoutEmailList())->toBe(['person@example.com'])
        ->and($named->fresh()->show_on_catalog)->toBeFalse()
        ->and($clear->fresh()->assigned_email)->toBeNull()
        ->and($clear->fresh()->checkoutEmailList())->toBe([])
        ->and(CourseCoupon::query()->where('code', 'BAD')->exists())->toBeFalse()
        ->and(CourseCoupon::query()->where('code', 'OPENROW')->first()?->assigned_email)->toBeNull();
});

it('lets every listed email share one code and stores each address once', function () {
    $admin = couponAdmin();
    $ada = couponStudent('Ada@Example.com');
    $bea = couponStudent('bea@example.com');
    $other = couponStudent('other@example.com');
    $course = couponCourse();
    $service = app(CourseCouponService::class);

    $this->actingAs($admin)->post(route('course-coupons.store'), [
        'code' => 'TEAM10',
        'course_id' => $course->id,
        'discount_type' => 'percentage',
        'discount' => 10,
        'is_active' => true,
        'show_on_catalog' => true,
        'usage_limit' => 4,
        'assigned_emails' => " Ada@Example.com, bea@example.com\nada@example.com ",
    ])->assertRedirect(route('course-coupons.index'));

    $coupon = CourseCoupon::query()->where('code', 'TEAM10')->first();

    expect($coupon)->not->toBeNull()
        ->and($coupon->assigned_email)->toBeNull()
        ->and($coupon->checkoutEmailList())->toBe(['ada@example.com', 'bea@example.com'])
        ->and($coupon->show_on_catalog)->toBeFalse()
        ->and($coupon->code)->toBe('TEAM10')
        ->and((float) $coupon->discount)->toBe(10.0)
        ->and($coupon->usage_limit)->toBe(4);

    expect($service->resolveCouponForCheckout((string) $course->id, 'team10', $ada->id)['error'])->toBeNull()
        ->and(payableDiscount($service->resolveCouponForCheckout((string) $course->id, 'TEAM10', $ada->id)['coupon']))->toBe(10.0)
        ->and($service->resolveCouponForCheckout((string) $course->id, 'TEAM10', $bea->id)['error'])->toBeNull()
        ->and($service->resolveCouponForCheckout((string) $course->id, 'TEAM10', $other->id)['error'])->toBe('This coupon is assigned to a different email.')
        ->and($service->resolveCouponForCheckout((string) $course->id, 'TEAM10', null)['error'])->toBe('Sign in with the email this coupon is assigned to.');

    PaymentHistory::create([
        'user_id' => $ada->id,
        'coupon' => 'TEAM10',
        'amount' => 90,
        'purchase_type' => Course::class,
        'purchase_id' => $course->id,
    ]);

    expect($service->resolveCouponForCheckout((string) $course->id, 'TEAM10', $ada->id)['error'])
        ->toBe('You have already used this voucher for this course.')
        ->and($service->resolveCouponForCheckout((string) $course->id, 'TEAM10', $bea->id)['error'])->toBeNull();
});

it('rejects an invalid address and does not save part of the list', function () {
    $admin = couponAdmin();
    $course = couponCourse();
    $coupon = openCoupon([
        'code' => 'KEEP10',
        'course_id' => $course->id,
        'discount' => 10,
        'assigned_email' => 'keep@example.com',
    ]);
    $coupon->replaceAssignedEmails(['keep@example.com']);
    $payment = PaymentHistory::create([
        'user_id' => couponStudent('keep@example.com')->id,
        'coupon' => 'KEEP10',
        'amount' => 90,
        'purchase_type' => Course::class,
        'purchase_id' => $course->id,
    ]);

    $exception = submitCouponUpdate($admin, $coupon, [
        'code' => 'KEEP10',
        'course_id' => $course->id,
        'discount_type' => 'percentage',
        'discount' => 25,
        'is_active' => false,
        'assigned_emails' => "keep@example.com, not-an-email",
    ]);

    expect($exception)->toBeInstanceOf(ValidationException::class)
        ->and($exception->errors()['assigned_emails'][0] ?? null)->toBe('Enter a valid email address for every person on the list.');

    $fresh = $coupon->fresh();

    expect($fresh->checkoutEmailList())->toBe(['keep@example.com'])
        ->and($fresh->code)->toBe('KEEP10')
        ->and((float) $fresh->discount)->toBe(10.0)
        ->and($fresh->is_active)->toBeTrue()
        ->and((float) $payment->fresh()->amount)->toBe(90.0);
});

it('clears the list back to an open coupon and can show it on the catalog again', function () {
    $admin = couponAdmin();
    $course = couponCourse();
    $coupon = openCoupon([
        'code' => 'OPENAGAIN',
        'course_id' => $course->id,
        'discount' => 15,
        'show_on_catalog' => false,
        'assigned_email' => 'ada@example.com',
    ]);
    $coupon->replaceAssignedEmails(['ada@example.com', 'bea@example.com']);
    $coupon->forceFill(['assigned_email' => null])->save();

    $exception = submitCouponUpdate($admin, $coupon, [
        'code' => 'OPENAGAIN',
        'course_id' => $course->id,
        'discount_type' => 'percentage',
        'discount' => 15,
        'is_active' => true,
        'show_on_catalog' => true,
        'assigned_emails' => '   ',
    ]);

    expect($exception)->toBeNull();

    $fresh = $coupon->fresh();
    $service = app(CourseCouponService::class);
    $student = couponStudent('someone@example.com');

    expect($fresh->checkoutEmailList())->toBe([])
        ->and($fresh->assigned_email)->toBeNull()
        ->and($fresh->show_on_catalog)->toBeTrue()
        ->and($service->resolveCouponForCheckout((string) $course->id, 'OPENAGAIN', $student->id)['error'])->toBeNull()
        ->and($service->featuredCatalogCouponFor($course)?->id)->toBe($fresh->id);
});

it('hides a shared code from the catalog and public suggestions', function () {
    $course = couponCourse();
    $open = openCoupon([
        'code' => 'PUBLIC',
        'course_id' => $course->id,
        'show_on_catalog' => true,
        'discount' => 15,
    ]);
    $shared = openCoupon([
        'code' => 'SHARED',
        'course_id' => $course->id,
        'show_on_catalog' => true,
        'discount' => 50,
    ]);
    $shared->replaceAssignedEmails(['ada@example.com', 'bea@example.com']);

    $service = app(CourseCouponService::class);
    $service->appendCatalogPromos($course);

    expect($service->featuredCatalogCouponFor($course)?->id)->toBe($open->id)
        ->and($service->getCourseValidCoupons((string) $course->id)->pluck('code')->all())->toBe(['PUBLIC'])
        ->and(json_encode($course->getAttribute('catalog_promo')))->not->toContain('SHARED');
});

it('imports several emails onto one existing code', function () {
    $admin = couponAdmin();
    $course = couponCourse();
    $coupon = openCoupon([
        'code' => 'TEAM10',
        'course_id' => $course->id,
        'discount' => 5,
        'usage_limit' => 3,
        'is_active' => true,
    ]);
    $ada = couponStudent('ada@example.com');
    $bea = couponStudent('bea@example.com');

    $csv = implode("\n", [
        'code,discount_type,discount,course_id,email',
        'TEAM10,percentage,10,'.$course->id.',"Ada@Example.com, bea@example.com; ada@example.com"',
        'BROKEN,percentage,10,'.$course->id.',"good@example.com, not-an-email"',
    ]);

    $this->actingAs($admin)->post(route('course-coupons.import'), [
        'csv_content' => $csv,
        'import_mode' => 'csv',
        'on_duplicate' => 'update',
    ])->assertRedirect(route('course-coupons.index'));

    $fresh = $coupon->fresh();

    expect(CourseCoupon::query()->where('code', 'TEAM10')->count())->toBe(1)
        ->and(CourseCoupon::query()->where('code', 'BROKEN')->exists())->toBeFalse()
        ->and($fresh->checkoutEmailList())->toBe(['ada@example.com', 'bea@example.com'])
        ->and($fresh->assigned_email)->toBeNull()
        ->and((float) $fresh->discount)->toBe(10.0)
        ->and($fresh->usage_limit)->toBe(3)
        ->and($fresh->course_id)->toBe($course->id)
        ->and(app(CourseCouponService::class)->resolveCouponForCheckout((string) $course->id, 'TEAM10', $ada->id)['error'])->toBeNull()
        ->and(app(CourseCouponService::class)->resolveCouponForCheckout((string) $course->id, 'TEAM10', $bea->id)['error'])->toBeNull();
});

it('keeps the overall usage limit ahead of the email list', function () {
    $course = couponCourse();
    $bea = couponStudent('bea@example.com');
    $coupon = openCoupon([
        'code' => 'ONCE',
        'course_id' => $course->id,
        'usage_type' => 'limited',
        'usage_limit' => 1,
        'used_count' => 1,
    ]);
    $coupon->replaceAssignedEmails(['ada@example.com', 'bea@example.com']);

    expect(app(CourseCouponService::class)->resolveCouponForCheckout((string) $course->id, 'ONCE', $bea->id)['error'])
        ->toBe('This coupon is not valid or has expired.');
});

it('falls back to the single assigned email when the list table cannot be read', function () {
    $course = couponCourse();
    $owner = couponStudent('student@example.com');
    $other = couponStudent('other@example.com');
    $coupon = openCoupon([
        'code' => 'LOCK10',
        'course_id' => $course->id,
        'assigned_email' => 'student@example.com',
        'show_on_catalog' => true,
    ]);
    Schema::drop('course_coupon_assigned_emails');

    $service = app(CourseCouponService::class);

    expect($coupon->assignedEmailList())->toBeNull()
        ->and($service->resolveCouponForCheckout((string) $course->id, 'LOCK10', $owner->id)['error'])->toBeNull()
        ->and($service->resolveCouponForCheckout((string) $course->id, 'LOCK10', $other->id)['error'])->toBe('This coupon is assigned to a different email.')
        ->and($service->resolveCouponForCheckout((string) $course->id, 'LOCK10', null)['error'])->toBe('Sign in with the email this coupon is assigned to.')
        ->and($service->getCourseValidCoupons((string) $course->id)->pluck('code')->all())->toBe([]);
});
