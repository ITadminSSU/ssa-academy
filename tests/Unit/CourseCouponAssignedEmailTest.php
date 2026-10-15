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
use Modules\PaymentGateways\Models\PaymentHistory;

beforeEach(function () {
    $this->withoutMiddleware();

    foreach (['payment_histories', 'course_coupons', 'courses', 'users'] as $table) {
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
        ->and($named->fresh()->show_on_catalog)->toBeFalse()
        ->and($clear->fresh()->assigned_email)->toBeNull()
        ->and(CourseCoupon::query()->where('code', 'BAD')->exists())->toBeFalse()
        ->and(CourseCoupon::query()->where('code', 'OPENROW')->first()?->assigned_email)->toBeNull();
});
