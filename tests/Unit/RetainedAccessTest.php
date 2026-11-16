<?php

use App\Enums\CourseBillingModel;
use App\Enums\EnrollmentAccessStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\RetainedAccessService;
use App\Services\Payment\SubscriptionAccessService;
use App\Services\Payment\SubscriptionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('course_enrollments');
    Schema::dropIfExists('subscriptions');
    Schema::dropIfExists('courses');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('role')->nullable();
        $table->timestamps();
    });

    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('slug')->nullable();
        $table->string('pricing_type')->nullable();
        $table->string('billing_model')->nullable();
        $table->timestamps();
    });

    Schema::create('subscriptions', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->string('stripe_customer_id')->nullable();
        $table->string('stripe_subscription_id')->nullable();
        $table->string('stripe_price_id')->nullable();
        $table->string('status');
        $table->timestamp('access_retained_at')->nullable();
        $table->timestamp('choice_installment_completed_at')->nullable();
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
        $table->string('access_status');
        $table->timestamp('suspended_at')->nullable();
        $table->unsignedBigInteger('subscription_id')->nullable();
        $table->timestamps();
    });
});

it('keeps full course access after a canceled stripe subscription', function () {
    $user = User::query()->create(['name' => 'A Student', 'email' => 'student@example.com', 'role' => 'student']);
    $course = Course::query()->create([
        'title' => 'Estimating',
        'slug' => 'estimating',
        'pricing_type' => 'paid',
        'billing_model' => CourseBillingModel::ONE_TIME_OR_MONTHLY->value,
    ]);
    $subscription = Subscription::query()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'stripe_customer_id' => 'cus_old',
        'stripe_subscription_id' => 'sub_old',
        'stripe_price_id' => 'price_old',
        'status' => SubscriptionStatus::CANCELED,
        'canceled_at' => now(),
    ]);
    $enrollment = CourseEnrollment::query()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'enrollment_type' => 'subscription',
        'access_status' => EnrollmentAccessStatus::SUSPENDED,
        'suspended_at' => now(),
        'subscription_id' => $subscription->id,
    ]);

    $kept = app(RetainedAccessService::class)->keepCourse($enrollment);

    expect($kept->access_status)->toBe(EnrollmentAccessStatus::ACTIVE)
        ->and($kept->enrollment_type)->toBe('paid')
        ->and($kept->suspended_at)->toBeNull()
        ->and($kept->subscription->accessRetained())->toBeTrue()
        ->and($kept->subscription->status)->toBe(SubscriptionStatus::CANCELED);

    app(SubscriptionService::class)->suspend($kept->subscription->fresh());

    $afterWebhook = $enrollment->fresh();

    expect($afterWebhook->access_status)->toBe(EnrollmentAccessStatus::ACTIVE)
        ->and($afterWebhook->enrollment_type)->toBe('paid');

    $mode = app(SubscriptionAccessService::class)->getAccessMode($user, $course->fresh(), $afterWebhook->fresh('subscription'));

    expect($mode)->toBe('full');
});

it('keeps a payment-plan course open when billing was stopped on purpose', function () {
    $user = new User(['id' => 4, 'role' => 'student']);
    $course = new Course([
        'billing_model' => CourseBillingModel::ONE_TIME_OR_INSTALLMENT,
        'pricing_type' => 'paid',
    ]);
    $subscription = new Subscription([
        'status' => SubscriptionStatus::CANCELED,
        'access_retained_at' => now(),
    ]);
    $enrollment = new CourseEnrollment([
        'access_status' => EnrollmentAccessStatus::ACTIVE,
        'enrollment_type' => 'paid',
    ]);
    $enrollment->setRelation('subscription', $subscription);

    expect(app(SubscriptionAccessService::class)->getAccessMode($user, $course, $enrollment))->toBe('full');
});
