<?php

use App\Enums\CourseBillingModel;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course\Course;
use App\Services\Course\CourseService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('courses');
});

it('saves one-time or monthly over an installment course and clears the installment schedule', function () {
    createChoiceBillingCourseTable();

    $course = installmentCourse();
    $request = pricingSaveRequest([
        'billing_model' => 'one_time_or_monthly',
        'price' => 49,
        'subscription_price' => 6,
        'installment_price' => 15,
        'installment_months' => 6,
        'choice_one_time_compare' => 99,
        'choice_recurring_compare' => 20,
    ]);
    $request->validateResolved();

    app(CourseService::class)->updateCourse((string) $course->id, $request->validated());

    $saved = Course::query()->findOrFail($course->id);

    expect($saved->billing_model)->toBe(CourseBillingModel::ONE_TIME_OR_MONTHLY)
        ->and((float) $saved->price)->toBe(49.0)
        ->and((float) $saved->subscription_price)->toBe(6.0)
        ->and($saved->installment_price)->toBeNull()
        ->and($saved->installment_months)->toBeNull()
        ->and((float) $saved->choice_one_time_compare)->toBe(99.0)
        ->and((float) $saved->choice_recurring_compare)->toBe(20.0)
        ->and($saved->stripe_price_id)->toBe('price_installment')
        ->and($saved->stripe_ongoing_price_id)->toBe('price_ongoing');
});

it('saves one-time or installment and clears the later monthly price', function () {
    createChoiceBillingCourseTable();

    $course = installmentCourse();
    $request = pricingSaveRequest([
        'billing_model' => 'one_time_or_installment',
        'price' => 49,
        'subscription_price' => 6,
        'installment_price' => 15,
        'installment_months' => 6,
        'choice_one_time_compare' => 99,
        'choice_recurring_compare' => 20,
    ]);
    $request->validateResolved();

    app(CourseService::class)->updateCourse((string) $course->id, $request->validated());

    $saved = Course::query()->findOrFail($course->id);

    expect($saved->billing_model)->toBe(CourseBillingModel::ONE_TIME_OR_INSTALLMENT)
        ->and((float) $saved->price)->toBe(49.0)
        ->and($saved->subscription_price)->toBeNull()
        ->and((float) $saved->installment_price)->toBe(15.0)
        ->and((int) $saved->installment_months)->toBe(6)
        ->and((float) $saved->choice_recurring_compare)->toBe(20.0)
        ->and($saved->stripe_product_id)->toBe('prod_installment');
});

it('saves a choice billing model when the compare price columns are missing', function () {
    createChoiceBillingCourseTable(false);

    $course = installmentCourse();
    $request = pricingSaveRequest([
        'billing_model' => 'one_time_or_monthly',
        'price' => 49,
        'subscription_price' => 6,
        'installment_price' => 15,
        'installment_months' => 6,
    ]);
    $request->validateResolved();

    app(CourseService::class)->updateCourse((string) $course->id, $request->validated());

    $saved = Course::query()->findOrFail($course->id);

    expect($saved->billing_model)->toBe(CourseBillingModel::ONE_TIME_OR_MONTHLY)
        ->and($saved->installment_price)->toBeNull()
        ->and($saved->installment_months)->toBeNull()
        ->and((float) $saved->subscription_price)->toBe(6.0);
});

function pricingSaveRequest(array $overrides = []): UpdateCourseRequest
{
    $request = UpdateCourseRequest::create('/courses/update', 'POST', array_merge([
        'tab' => 'pricing',
        'pricing_type' => 'paid',
        'billing_model' => 'installment_subscription',
        'price' => null,
        'subscription_price' => 6,
        'installment_price' => 15,
        'installment_months' => 6,
        'expiry_type' => 'lifetime',
        'discount' => false,
        'launch_offer_enabled' => true,
        'launch_offer_starts_at' => '2026-08-15T00:00',
        'launch_offer_ends_at' => '2026-09-14T23:59',
        'launch_list_price' => 75,
        'launch_offer_price' => 70,
        'launch_deposit_amount' => 20,
        'launch_balance_amount' => 50,
        'launch_full_upfront_price' => 75,
        'catalog_coupon_promo' => true,
        'catalog_coupon_off_remaining' => 29,
    ], $overrides));
    $request->headers->set('Accept', 'application/json');
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    return $request;
}

function installmentCourse(): Course
{
    return Course::query()->create([
        'title' => 'Copied estimating course',
        'slug' => 'copied-estimating-course',
        'status' => 'approved',
        'pricing_type' => 'paid',
        'billing_model' => CourseBillingModel::INSTALLMENT_SUBSCRIPTION->value,
        'price' => null,
        'subscription_price' => 6,
        'installment_price' => 15,
        'installment_months' => 6,
        'stripe_product_id' => 'prod_installment',
        'stripe_price_id' => 'price_installment',
        'stripe_ongoing_price_id' => 'price_ongoing',
    ]);
}

function createChoiceBillingCourseTable(bool $choiceColumns = true): void
{
    Schema::create('courses', function (Blueprint $table) use ($choiceColumns) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('slug')->nullable();
        $table->string('status')->nullable();
        $table->string('pricing_type')->nullable();
        $table->string('billing_model')->nullable();
        $table->decimal('price', 10, 2)->nullable();
        $table->boolean('discount')->nullable();
        $table->decimal('discount_price', 10, 2)->nullable();
        $table->decimal('subscription_price', 10, 2)->nullable();
        $table->decimal('installment_price', 10, 2)->nullable();
        $table->unsignedSmallInteger('installment_months')->nullable();
        if ($choiceColumns) {
            $table->decimal('choice_one_time_compare', 10, 2)->nullable();
            $table->decimal('choice_recurring_compare', 10, 2)->nullable();
        }
        $table->string('stripe_product_id')->nullable();
        $table->string('stripe_price_id')->nullable();
        $table->string('stripe_ongoing_price_id')->nullable();
        $table->string('expiry_type')->nullable();
        $table->string('expiry_duration')->nullable();
        $table->boolean('launch_offer_enabled')->default(false);
        $table->timestamp('launch_offer_starts_at')->nullable();
        $table->timestamp('launch_offer_ends_at')->nullable();
        $table->decimal('launch_list_price', 10, 2)->nullable();
        $table->decimal('launch_offer_price', 10, 2)->nullable();
        $table->decimal('launch_deposit_amount', 10, 2)->nullable();
        $table->decimal('launch_balance_amount', 10, 2)->nullable();
        $table->unsignedTinyInteger('launch_balance_grace_days')->nullable();
        $table->timestamp('launch_subscription_trial_ends_at')->nullable();
        $table->decimal('launch_full_upfront_price', 10, 2)->nullable();
        $table->boolean('catalog_coupon_promo')->default(false);
        $table->decimal('catalog_coupon_off_remaining', 10, 2)->nullable();
        $table->timestamps();
    });
}
