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
        ->and($saved->choice_best_value)->toBe('recurring')
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
        ->and($saved->choice_best_value)->toBe('recurring')
        ->and($saved->stripe_product_id)->toBe('prod_installment');
});

it('saves the best value badge on the one-time card without changing prices', function () {
    createChoiceBillingCourseTable();

    $course = installmentCourse();
    $request = pricingSaveRequest([
        'billing_model' => 'one_time_or_monthly',
        'price' => 49,
        'subscription_price' => 6,
        'choice_one_time_compare' => 99,
        'choice_best_value' => 'one_time',
    ]);
    $request->validateResolved();

    app(CourseService::class)->updateCourse((string) $course->id, $request->validated());

    $saved = Course::query()->findOrFail($course->id);

    expect($saved->choice_best_value)->toBe('one_time')
        ->and((float) $saved->price)->toBe(49.0)
        ->and((float) $saved->subscription_price)->toBe(6.0)
        ->and((float) $saved->choice_one_time_compare)->toBe(99.0)
        ->and($saved->stripe_price_id)->toBe('price_installment')
        ->and($saved->stripe_ongoing_price_id)->toBe('price_ongoing');
});

it('saves no best value badge and leaves the charge amounts alone', function () {
    createChoiceBillingCourseTable();

    $course = installmentCourse();
    $request = pricingSaveRequest([
        'billing_model' => 'one_time_or_installment',
        'price' => 49,
        'installment_price' => 15,
        'installment_months' => 6,
        'choice_best_value' => 'none',
    ]);
    $request->validateResolved();

    app(CourseService::class)->updateCourse((string) $course->id, $request->validated());

    $saved = Course::query()->findOrFail($course->id);

    expect($saved->choice_best_value)->toBe('none')
        ->and((float) $saved->price)->toBe(49.0)
        ->and((float) $saved->installment_price)->toBe(15.0)
        ->and((int) $saved->installment_months)->toBe(6)
        ->and($saved->stripe_product_id)->toBe('prod_installment');
});

it('does not keep a best value badge on a billing model that has one price', function () {
    createChoiceBillingCourseTable();

    $course = installmentCourse();
    $course->forceFill(['choice_best_value' => 'one_time'])->save();
    $request = pricingSaveRequest([
        'billing_model' => 'one_time',
        'price' => 49,
        'choice_best_value' => 'one_time',
    ]);
    $request->validateResolved();

    app(CourseService::class)->updateCourse((string) $course->id, $request->validated());

    $saved = Course::query()->findOrFail($course->id);

    expect($saved->billing_model)->toBe(CourseBillingModel::ONE_TIME)
        ->and($saved->choice_best_value)->toBeNull()
        ->and((float) $saved->price)->toBe(49.0)
        ->and($saved->stripe_price_id)->toBe('price_installment');
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

it('shows the best value badge on the card the course saved', function () {
    $item = new class {
        public int $id = 9;
    };
    $formatMoney = fn (float $amount) => 'USD '.number_format($amount, 2);

    $monthly = view('paymentgateways::partials.payment.ChoicePlans', [
        'choicePlan' => [
            'plan' => 'recurring',
            'mode' => 'one_time_or_monthly',
            'one_time' => 49,
            'one_time_compare' => 99,
            'recurring' => 6,
            'recurring_compare' => null,
            'months' => 0,
            'recurring_synced' => true,
            'best_value' => 'one_time',
        ],
        'item' => $item,
        'item_type' => 'course',
        'coupon' => null,
        'formatMoney' => $formatMoney,
    ])->render();

    expect(substr_count($monthly, 'Best value'))->toBe(1)
        ->and(strpos($monthly, 'Best value'))->toBeLessThan(strpos($monthly, 'Monthly plan'));

    $hidden = view('paymentgateways::partials.payment.ChoicePlans', [
        'choicePlan' => [
            'plan' => 'recurring',
            'mode' => 'one_time_or_monthly',
            'one_time' => 49,
            'recurring' => 6,
            'months' => 0,
            'recurring_synced' => true,
            'best_value' => 'none',
        ],
        'item' => $item,
        'item_type' => 'course',
        'coupon' => null,
        'formatMoney' => $formatMoney,
    ])->render();

    expect($hidden)->not->toContain('Best value')
        ->and($hidden)->toContain('USD 49.00')
        ->and($hidden)->toContain('USD 6.00');

    $missing = view('paymentgateways::partials.payment.ChoicePlans', [
        'choicePlan' => [
            'plan' => 'one_time',
            'mode' => 'one_time_or_monthly',
            'one_time' => 49,
            'recurring' => 15,
            'months' => 0,
            'recurring_synced' => true,
        ],
        'item' => $item,
        'item_type' => 'course',
        'coupon' => null,
        'formatMoney' => $formatMoney,
    ])->render();

    expect($missing)->not->toContain('Best value')
        ->and($missing)->toContain('USD 49.00')
        ->and($missing)->toContain('USD 15.00');

    expect(substr_count($monthly, 'choice-plan-radio-selected'))->toBe(1)
        ->and(strpos($monthly, 'choice-plan-radio-selected'))->toBeGreaterThan(strpos($monthly, 'One-Time Payment'))
        ->and(strpos($monthly, 'choice-plan-radio-selected'))->toBeLessThan(strpos($monthly, 'Monthly plan'));

    $oneTimeSelected = view('paymentgateways::partials.payment.ChoicePlans', [
        'choicePlan' => [
            'plan' => 'one_time',
            'mode' => 'one_time_or_monthly',
            'one_time' => 49,
            'recurring' => 15,
            'months' => 0,
            'recurring_synced' => true,
            'best_value' => 'recurring',
        ],
        'item' => $item,
        'item_type' => 'course',
        'coupon' => null,
        'formatMoney' => $formatMoney,
    ])->render();

    expect(substr_count($oneTimeSelected, 'choice-plan-radio-selected'))->toBe(1)
        ->and(strpos($oneTimeSelected, 'choice-plan-radio-selected'))->toBeLessThan(strpos($oneTimeSelected, 'One-Time Payment'))
        ->and($oneTimeSelected)->toContain('plan=one_time')
        ->and($oneTimeSelected)->toContain('plan=recurring')
        ->and($oneTimeSelected)->toContain('USD 49.00')
        ->and($oneTimeSelected)->toContain('USD 15.00');
});

it('describes the selected plan under the checkout total', function () {
    $item = new class
    {
        public int $id = 36;

        public string $pricing_type = 'paid';

        public string $title = 'Copy of Test Duplicate course';

        public string $short_description = 'A short description';

        public bool $discount = false;
    };

    $once = checkoutCardHtml($item, [
        'checkoutMode' => 'one_time_or_monthly',
        'discountedPrice' => 49,
        'choice' => [
            'plan' => 'one_time',
            'mode' => 'one_time_or_monthly',
            'one_time' => 49,
            'one_time_compare' => 99,
            'recurring' => 15,
            'recurring_compare' => 20,
            'months' => 0,
            'recurring_synced' => true,
            'best_value' => 'recurring',
        ],
    ]);

    expect($once)->toContain('You pay USD 49.00 once.')
        ->and($once)->toContain('USD 49.00')
        ->and($once)->toContain('USD 15.00')
        ->and(substr_count($once, 'Best value'))->toBe(1)
        ->and(strpos($once, 'Best value'))->toBeGreaterThan(strpos($once, 'One-Time Payment'))
        ->and($once)->toContain('Enter coupon code if you have one')
        ->and(substr_count($once, 'Total Payment'))->toBe(1);

    $monthly = checkoutCardHtml($item, [
        'checkoutMode' => 'one_time_or_monthly',
        'discountedPrice' => 15,
        'choice' => [
            'plan' => 'recurring',
            'mode' => 'one_time_or_monthly',
            'one_time' => 49,
            'recurring' => 15,
            'months' => 0,
            'recurring_synced' => true,
            'best_value' => 'one_time',
        ],
    ]);

    expect($monthly)->toContain('You pay USD 15.00 today, then USD 15.00 each month until you cancel.')
        ->and(substr_count($monthly, 'Best value'))->toBe(1)
        ->and(strpos($monthly, 'Best value'))->toBeLessThan(strpos($monthly, 'One-Time Payment'));

    $installment = checkoutCardHtml($item, [
        'checkoutMode' => 'one_time_or_installment',
        'discountedPrice' => 20,
        'choice' => [
            'plan' => 'recurring',
            'mode' => 'one_time_or_installment',
            'one_time' => 120,
            'recurring' => 20,
            'months' => 6,
            'recurring_synced' => true,
            'best_value' => 'none',
        ],
    ]);

    expect($installment)->toContain('You pay USD 20.00 today, then 6 payments. Billing stops after the last one.')
        ->and($installment)->not->toContain('Best value')
        ->and($installment)->toContain('Payment plan')
        ->and($installment)->toContain('USD 120.00')
        ->and($installment)->toContain('USD 20.00');
});

function checkoutCardHtml(object $item, array $overrides): string
{
    return view('paymentgateways::partials.payment.CourseCard', array_merge([
        'item' => $item,
        'item_type' => 'course',
        'currency' => 'USD',
        'discountedPrice' => 0,
        'coupon' => null,
        'couponDiscount' => 0,
        'couponInput' => null,
        'couponError' => null,
        'checkoutMode' => null,
        'choice' => null,
        'launchOffer' => null,
    ], $overrides))->render();
}

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
            $table->string('choice_best_value', 20)->nullable();
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
