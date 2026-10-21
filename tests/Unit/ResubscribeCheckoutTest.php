<?php

use App\Enums\CourseBillingModel;
use App\Enums\EnrollmentAccessStatus;
use App\Enums\LearnerUserType;
use App\Enums\SubscriptionStatus;
use App\Http\Middleware\EnforceSingleSession;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Course\Course;
use App\Models\Course\CourseCategory;
use App\Models\Course\CourseEnrollment;
use App\Models\Course\WatchHistory;
use App\Models\Instructor;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Course\CourseSectionService;
use App\Services\Payment\LaunchOfferEnrollmentService;
use App\Services\Payment\SubscriptionAccessService;
use App\Services\Payment\SubscriptionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Cache::flush();
    config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    app()->forgetInstance('encrypter');
    $this->withoutVite();
    $this->withoutMiddleware([
        EnforceSingleSession::class,
        HandleInertiaRequests::class,
    ]);

    foreach ([
        'watch_histories',
        'course_enrollments',
        'subscriptions',
        'courses',
        'course_categories',
        'instructors',
        'settings',
        'users',
    ] as $table) {
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
        $table->unsignedBigInteger('instructor_id')->nullable();
        $table->integer('status')->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('remember_token')->nullable();
        $table->timestamps();
    });

    Schema::create('instructors', function (Blueprint $table) {
        $table->id();
        $table->json('skills')->nullable();
        $table->text('biography')->nullable();
        $table->string('resume')->nullable();
        $table->string('designation')->nullable();
        $table->string('status')->nullable();
        $table->json('payout_methods')->nullable();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('course_categories', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('slug');
        $table->integer('sort')->default(0);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('slug');
        $table->string('course_type')->nullable();
        $table->string('status')->nullable();
        $table->timestamp('launch_at')->nullable();
        $table->string('level')->nullable();
        $table->text('short_description')->nullable();
        $table->string('pricing_type')->nullable();
        $table->string('audience')->nullable();
        $table->string('billing_model')->nullable();
        $table->decimal('price', 10, 2)->nullable();
        $table->boolean('discount')->default(false);
        $table->decimal('discount_price', 10, 2)->nullable();
        $table->decimal('subscription_price', 10, 2)->nullable();
        $table->string('stripe_price_id')->nullable();
        $table->boolean('launch_offer_enabled')->default(false);
        $table->timestamp('launch_offer_starts_at')->nullable();
        $table->timestamp('launch_offer_ends_at')->nullable();
        $table->decimal('launch_list_price', 10, 2)->nullable();
        $table->decimal('launch_deposit_amount', 10, 2)->nullable();
        $table->decimal('launch_balance_amount', 10, 2)->nullable();
        $table->unsignedSmallInteger('launch_balance_grace_days')->default(5);
        $table->decimal('launch_full_upfront_price', 10, 2)->nullable();
        $table->string('thumbnail')->nullable();
        $table->unsignedBigInteger('instructor_id')->nullable();
        $table->unsignedBigInteger('course_category_id')->nullable();
        $table->timestamps();
    });

    Schema::create('subscriptions', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->string('stripe_customer_id');
        $table->string('stripe_subscription_id')->unique();
        $table->string('stripe_price_id');
        $table->string('status');
        $table->timestamp('current_period_start')->nullable();
        $table->timestamp('current_period_end')->nullable();
        $table->boolean('cancel_at_period_end')->default(false);
        $table->timestamp('canceled_at')->nullable();
        $table->timestamp('grace_ends_at')->nullable();
        $table->timestamps();
    });

    Schema::create('course_enrollments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->string('enrollment_type');
        $table->string('access_status')->default('active');
        $table->timestamp('entry_date')->nullable();
        $table->timestamp('suspended_at')->nullable();
        $table->unsignedBigInteger('subscription_id')->nullable();
        $table->decimal('deposit_amount', 10, 2)->nullable();
        $table->timestamp('deposit_paid_at')->nullable();
        $table->decimal('balance_amount', 10, 2)->nullable();
        $table->timestamp('balance_paid_at')->nullable();
        $table->timestamp('forfeited_at')->nullable();
        $table->timestamp('welcome_email_sent_at')->nullable();
        $table->timestamps();
    });

    Schema::create('watch_histories', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->string('current_section_id');
        $table->string('current_watching_id');
        $table->string('current_watching_type');
        $table->json('completed_watching')->nullable();
        $table->json('lesson_watch_progress')->nullable();
        $table->timestamps();
    });

    Schema::create('settings', function (Blueprint $table) {
        $table->id();
        $table->string('type')->nullable();
        $table->string('sub_type')->nullable();
        $table->string('title')->nullable();
        $table->json('fields')->nullable();
        $table->timestamps();
    });

    Setting::query()->create([
        'type' => 'system',
        'sub_type' => 'system',
        'title' => 'System',
        'fields' => [
            'selling_tax' => 0,
            'selling_currency' => 'USD',
            'name' => 'SSA Academy',
            'instructor_revenue' => 0,
        ],
    ]);

    foreach (['stripe', 'bank_transfer', 'wire_transfer'] as $gateway) {
        Setting::query()->create([
            'type' => 'payment',
            'sub_type' => $gateway,
            'title' => $gateway,
            'fields' => ['active' => true],
        ]);
    }

    app()->forgetInstance('system_settings');
});

it('opens monthly resubscribe checkout for a suspended subscription enrollment', function () {
    $user = resubscribeStudent();
    $course = resubscribeCourse([
        'billing_model' => CourseBillingModel::UPFRONT_SUBSCRIPTION,
        'price' => 480,
        'subscription_price' => 6,
        'launch_offer_enabled' => true,
        'launch_offer_ends_at' => now()->subMonth(),
        'launch_full_upfront_price' => 75,
        'stripe_price_id' => 'price_monthly',
    ]);
    resubscribeEnrollment($user, $course, EnrollmentAccessStatus::SUSPENDED, SubscriptionStatus::CANCELED);

    $response = $this->actingAs($user)->get(route('payments.index', [
        'from' => 'web',
        'item' => 'course',
        'id' => $course->id,
        'coupon' => 'SAVE50',
    ]));

    $response->assertOk();
    $response->assertSee('6.00');
    $response->assertSee('Pay the monthly Project Plans price today to restore full access.');
    $response->assertSee('Stripe');
    $response->assertDontSee('480.00');
    $response->assertDontSee('75.00');
    $response->assertDontSee('Bank Transfer');
    $response->assertDontSee('Wire Transfer');
    $response->assertDontSee('Pay this enrollment amount plus the first month of Project Plans today');
    $response->assertDontSee('Coupon Applied');
    expect(app(LaunchOfferEnrollmentService::class)->resolveCheckoutMode($user, $course))->toBe('resubscribe');
});

it('keeps an active enrollment and a one-time enrollment on the already-enrolled message', function () {
    $activeStudent = resubscribeStudent();
    $subscriptionCourse = resubscribeCourse([
        'billing_model' => CourseBillingModel::UPFRONT_SUBSCRIPTION,
        'price' => 480,
        'subscription_price' => 6,
    ]);
    resubscribeEnrollment($activeStudent, $subscriptionCourse, EnrollmentAccessStatus::ACTIVE, SubscriptionStatus::ACTIVE);

    $this->actingAs($activeStudent)
        ->get(route('payments.index', ['from' => 'web', 'item' => 'course', 'id' => $subscriptionCourse->id]))
        ->assertRedirect(route('course.details', ['slug' => $subscriptionCourse->slug, 'id' => $subscriptionCourse->id]))
        ->assertSessionHas('info', 'You are already enrolled in this course.');

    expect(app(SubscriptionAccessService::class)->toFrontendPayload($activeStudent, $subscriptionCourse)['can_resubscribe'])->toBeFalse();

    $graceStudent = resubscribeStudent();
    $graceCourse = resubscribeCourse([
        'billing_model' => CourseBillingModel::SUBSCRIPTION,
        'price' => 6,
        'subscription_price' => 6,
    ]);
    resubscribeEnrollment(
        $graceStudent,
        $graceCourse,
        EnrollmentAccessStatus::SUSPENDED,
        SubscriptionStatus::PAST_DUE,
        now()->addDay(),
    );

    $this->actingAs($graceStudent)
        ->get(route('payments.index', ['from' => 'web', 'item' => 'course', 'id' => $graceCourse->id]))
        ->assertRedirect(route('course.details', ['slug' => $graceCourse->slug, 'id' => $graceCourse->id]))
        ->assertSessionHas('info', 'You are already enrolled in this course.');

    expect(app(SubscriptionAccessService::class)->toFrontendPayload($graceStudent, $graceCourse)['can_resubscribe'])->toBeFalse();

    $oneTimeStudent = resubscribeStudent();
    $oneTimeCourse = resubscribeCourse([
        'billing_model' => CourseBillingModel::ONE_TIME,
        'price' => 120,
        'subscription_price' => null,
    ]);
    CourseEnrollment::query()->create([
        'user_id' => $oneTimeStudent->id,
        'course_id' => $oneTimeCourse->id,
        'enrollment_type' => 'paid',
        'access_status' => EnrollmentAccessStatus::ACTIVE,
        'entry_date' => now(),
    ]);

    $this->actingAs($oneTimeStudent)
        ->get(route('payments.index', ['from' => 'web', 'item' => 'course', 'id' => $oneTimeCourse->id]))
        ->assertRedirect(route('course.details', ['slug' => $oneTimeCourse->slug, 'id' => $oneTimeCourse->id]))
        ->assertSessionHas('info', 'You are already enrolled in this course.');
});

it('still charges a new upfront student the course price plus the first month', function () {
    $user = resubscribeStudent();
    $course = resubscribeCourse([
        'billing_model' => CourseBillingModel::UPFRONT_SUBSCRIPTION,
        'price' => 480,
        'subscription_price' => 6,
        'launch_offer_enabled' => false,
    ]);

    $response = $this->actingAs($user)->get(route('payments.index', [
        'from' => 'web',
        'item' => 'course',
        'id' => $course->id,
    ]));

    $response->assertOk();
    $response->assertSee('480.00');
    $response->assertSee('Pay this enrollment amount plus the first month of Project Plans today for full access.');
    $response->assertSee('Bank Transfer');
    $response->assertSee('Wire Transfer');
    $response->assertDontSee('Pay the monthly Project Plans price today to restore full access.');
    expect(app(LaunchOfferEnrollmentService::class)->resolveCheckoutMode($user, $course))->toBe('upfront_subscription');
});

it('still charges a new full-launch student the upfront price plus the first month', function () {
    $user = resubscribeStudent();
    $course = resubscribeCourse([
        'billing_model' => CourseBillingModel::UPFRONT_SUBSCRIPTION,
        'price' => 480,
        'subscription_price' => 6,
        'launch_offer_enabled' => true,
        'launch_full_upfront_price' => 75,
        'launch_offer_ends_at' => now()->subMonth(),
    ]);

    $response = $this->actingAs($user)->get(route('payments.index', [
        'from' => 'web',
        'item' => 'course',
        'id' => $course->id,
    ]));

    $response->assertOk();
    $response->assertSee('75.00');
    $response->assertSee('Pay this enrollment amount plus the first month of Project Plans today for full access.');
    $response->assertSee('Bank Transfer');
    $response->assertDontSee('Pay the monthly Project Plans price today to restore full access.');
    expect(app(LaunchOfferEnrollmentService::class)->resolveCheckoutMode($user, $course))->toBe('full_launch');
});

it('still charges a reserved seat only the remaining balance', function () {
    $user = resubscribeStudent();
    $course = resubscribeCourse([
        'billing_model' => CourseBillingModel::PRE_REGISTER_SUBSCRIPTION,
        'price' => 480,
        'subscription_price' => 6,
        'launch_offer_enabled' => true,
        'launch_at' => now()->subDay(),
        'launch_balance_amount' => 50,
        'launch_deposit_amount' => 20,
        'launch_list_price' => 70,
        'launch_full_upfront_price' => 75,
        'launch_balance_grace_days' => 5,
        'launch_offer_ends_at' => now()->subMonth(),
    ]);

    CourseEnrollment::query()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'enrollment_type' => 'deposit',
        'access_status' => EnrollmentAccessStatus::RESERVED,
        'entry_date' => now()->subMonth(),
        'deposit_amount' => 20,
        'deposit_paid_at' => now()->subMonth(),
        'balance_amount' => 50,
    ]);

    $response = $this->actingAs($user)->get(route('payments.index', [
        'from' => 'web',
        'item' => 'course',
        'id' => $course->id,
    ]));

    $response->assertOk();
    $response->assertSee('50.00');
    $response->assertSee('Launch balance');
    $response->assertSee('Balance due today');
    $response->assertDontSee('480.00');
    $response->assertDontSee('Pay the monthly Project Plans price today to restore full access.');
    expect(app(LaunchOfferEnrollmentService::class)->resolveCheckoutMode($user, $course))->toBe('balance');
});

it('keeps a canceled enrollment on its current checkout mode', function () {
    $user = resubscribeStudent();
    $course = resubscribeCourse([
        'billing_model' => CourseBillingModel::UPFRONT_SUBSCRIPTION,
        'price' => 480,
        'subscription_price' => 6,
        'launch_offer_enabled' => false,
    ]);

    CourseEnrollment::query()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'enrollment_type' => 'subscription',
        'access_status' => EnrollmentAccessStatus::CANCELED,
        'entry_date' => now()->subYear(),
    ]);

    $response = $this->actingAs($user)->get(route('payments.index', [
        'from' => 'web',
        'item' => 'course',
        'id' => $course->id,
    ]));

    $response->assertOk();
    $response->assertSee('480.00');
    $response->assertSee('Pay this enrollment amount plus the first month of Project Plans today for full access.');
    expect(app(LaunchOfferEnrollmentService::class)->resolveCheckoutMode($user, $course))->toBe('upfront_subscription');
});

it('keeps employee learners on the free enrollment message', function () {
    $user = resubscribeStudent(['user_type' => LearnerUserType::EMPLOYEE]);
    $course = resubscribeCourse([
        'billing_model' => CourseBillingModel::SUBSCRIPTION,
        'price' => 6,
        'subscription_price' => 6,
    ]);
    resubscribeEnrollment($user, $course, EnrollmentAccessStatus::SUSPENDED, SubscriptionStatus::CANCELED);

    $this->actingAs($user)
        ->get(route('payments.index', ['from' => 'web', 'item' => 'course', 'id' => $course->id]))
        ->assertRedirect(route('course.details', ['slug' => $course->slug, 'id' => $course->id]))
        ->assertSessionHas('info', 'Employee learners can enroll in this course for free from the course page.');
});

it('reactivates the same enrollment and keeps watch history after resubscribe', function () {
    $user = resubscribeStudent();
    $course = resubscribeCourse([
        'billing_model' => CourseBillingModel::UPFRONT_SUBSCRIPTION,
        'price' => 480,
        'subscription_price' => 6,
        'stripe_price_id' => 'price_monthly',
    ]);
    $enrollment = resubscribeEnrollment($user, $course, EnrollmentAccessStatus::SUSPENDED, SubscriptionStatus::CANCELED);
    $enrollment->update(['welcome_email_sent_at' => now()->subMonth()]);

    $progress = [
        '10' => ['percent' => 100, 'max_seconds' => 375, 'duration_seconds' => 375],
    ];
    $completed = [['id' => '10', 'type' => 'lesson']];
    $history = WatchHistory::query()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'current_section_id' => '1',
        'current_watching_id' => '10',
        'current_watching_type' => 'lesson',
        'completed_watching' => $completed,
        'lesson_watch_progress' => $progress,
    ]);

    $renewed = Subscription::query()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'stripe_customer_id' => 'cus_renew',
        'stripe_subscription_id' => 'sub_renew_'.$user->id,
        'stripe_price_id' => 'price_monthly',
        'status' => SubscriptionStatus::ACTIVE,
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
    ]);

    $method = new ReflectionMethod(SubscriptionService::class, 'syncEnrollment');
    $method->invoke(app(SubscriptionService::class), $renewed);

    expect(CourseEnrollment::query()->where('user_id', $user->id)->where('course_id', $course->id)->count())->toBe(1);

    $enrollment->refresh();
    expect($enrollment->access_status)->toBe(EnrollmentAccessStatus::ACTIVE);
    expect($enrollment->subscription_id)->toBe($renewed->id);
    expect($enrollment->suspended_at)->toBeNull();

    $history->refresh();
    expect($history->completed_watching)->toBe($completed);
    expect($history->lesson_watch_progress)->toBe($progress);

    $returned = app(CourseSectionService::class)->initWatchHistory((string) $course->id, 'lesson', (string) $user->id);
    expect($returned->id)->toBe($history->id);
    expect($returned->fresh()->lesson_watch_progress)->toBe($progress);
});

function resubscribeStudent(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Student',
        'email' => 'student-'.uniqid().'@example.com',
        'password' => 'password',
        'role' => 'student',
        'user_type' => LearnerUserType::EXTERNAL->value,
        'status' => 1,
        'email_verified_at' => now(),
    ], $overrides));
}

function resubscribeCourse(array $overrides = []): Course
{
    $instructorUser = User::query()->create([
        'name' => 'Trainer',
        'email' => 'trainer-'.uniqid().'@example.com',
        'password' => 'password',
        'role' => 'instructor',
        'user_type' => LearnerUserType::EXTERNAL->value,
        'status' => 1,
        'email_verified_at' => now(),
    ]);

    $instructor = Instructor::query()->create([
        'user_id' => $instructorUser->id,
        'skills' => ['estimating'],
        'biography' => 'Lead trainer',
        'resume' => 'resume.pdf',
        'designation' => 'Instructor',
        'status' => 'approved',
        'payout_methods' => [],
    ]);

    $category = CourseCategory::query()->create([
        'title' => 'Estimating',
        'slug' => 'estimating-'.uniqid(),
        'sort' => 1,
        'status' => true,
    ]);

    return Course::query()->create(array_merge([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-estimating-'.uniqid(),
        'course_type' => 'general',
        'status' => 'approved',
        'level' => 'beginner',
        'short_description' => 'Learn drywall estimating.',
        'pricing_type' => 'paid',
        'audience' => 'public',
        'billing_model' => CourseBillingModel::UPFRONT_SUBSCRIPTION,
        'price' => 480,
        'discount' => false,
        'subscription_price' => 6,
        'launch_offer_enabled' => false,
        'instructor_id' => $instructor->id,
        'course_category_id' => $category->id,
    ], $overrides));
}

function resubscribeEnrollment(
    User $user,
    Course $course,
    EnrollmentAccessStatus $status,
    SubscriptionStatus $subscriptionStatus,
    mixed $graceEndsAt = null,
): CourseEnrollment {
    $subscription = Subscription::query()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'stripe_customer_id' => 'cus_'.$user->id.'_'.$course->id,
        'stripe_subscription_id' => 'sub_'.$user->id.'_'.$course->id,
        'stripe_price_id' => $course->stripe_price_id ?: 'price_monthly',
        'status' => $subscriptionStatus,
        'grace_ends_at' => $graceEndsAt,
        'canceled_at' => $subscriptionStatus === SubscriptionStatus::CANCELED ? now()->subDay() : null,
    ]);

    return CourseEnrollment::query()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'enrollment_type' => 'subscription',
        'access_status' => $status,
        'entry_date' => now()->subMonths(3),
        'subscription_id' => $subscription->id,
        'suspended_at' => $status === EnrollmentAccessStatus::SUSPENDED ? now()->subDay() : null,
    ]);
}
