<?php

use App\Enums\CourseBillingModel;
use App\Enums\EnrollmentAccessStatus;
use App\Enums\LearnerUserType;
use App\Enums\SubscriptionStatus;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course\Course;
use App\Models\Course\CourseCoupon;
use App\Models\Course\CourseEnrollment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\SubscriptionAccessService;
use App\Support\ChoiceBilling;
use Illuminate\Validation\ValidationException;
use Modules\PaymentGateways\Services\PaymentService;

function choiceRequest(array $overrides = []): UpdateCourseRequest
{
    $request = UpdateCourseRequest::create('/courses/update', 'POST', array_merge([
        'tab' => 'pricing',
        'pricing_type' => 'paid',
        'billing_model' => 'one_time',
        'price' => 49,
        'expiry_type' => 'lifetime',
        'discount' => false,
        'launch_offer_enabled' => false,
        'catalog_coupon_promo' => false,
    ], $overrides));
    $request->headers->set('Accept', 'application/json');
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    return $request;
}

function choiceCourse(array $overrides = []): Course
{
    $course = new Course(array_merge([
        'id' => 8,
        'title' => 'Drywall',
        'slug' => 'drywall',
        'pricing_type' => 'paid',
        'billing_model' => CourseBillingModel::ONE_TIME_OR_MONTHLY,
        'price' => 49,
        'subscription_price' => 15,
        'choice_one_time_compare' => 99,
        'choice_recurring_compare' => 20,
        'stripe_price_id' => 'price_monthly',
    ], $overrides));
    $course->exists = true;

    return $course;
}

function choiceUser(): User
{
    $user = new User([
        'id' => 3,
        'role' => 'student',
        'user_type' => LearnerUserType::EXTERNAL,
    ]);
    $user->exists = true;

    return $user;
}

it('keeps a one-time course on the one-time price only', function () {
    $request = choiceRequest([
        'billing_model' => 'one_time',
        'price' => 80,
        'subscription_price' => 15,
        'installment_price' => 15,
        'installment_months' => 6,
        'choice_one_time_compare' => 100,
    ]);

    $request->validateResolved();

    expect($request->input('billing_model'))->toBe('one_time')
        ->and($request->input('price'))->toEqual(80)
        ->and($request->input('subscription_price'))->toBeNull()
        ->and($request->input('installment_price'))->toBeNull()
        ->and($request->input('choice_one_time_compare'))->toBeNull();
});

it('rejects a discounted display that is not lower than the charged price', function () {
    $request = choiceRequest([
        'billing_model' => 'one_time_or_monthly',
        'price' => 99,
        'subscription_price' => 15,
        'choice_one_time_compare' => 99,
    ]);

    expect(fn () => $request->validateResolved())->toThrow(ValidationException::class);
});

it('stores one-time or monthly prices without an installment schedule', function () {
    $request = choiceRequest([
        'billing_model' => 'one_time_or_monthly',
        'price' => 49,
        'subscription_price' => 15,
        'choice_one_time_compare' => 39,
        'choice_recurring_compare' => 10,
        'installment_price' => 15,
        'installment_months' => 6,
    ]);

    $request->validateResolved();

    expect($request->input('billing_model'))->toBe('one_time_or_monthly')
        ->and($request->input('price'))->toEqual(49)
        ->and($request->input('subscription_price'))->toEqual(15)
        ->and($request->input('choice_one_time_compare'))->toEqual(39)
        ->and($request->input('choice_recurring_compare'))->toEqual(10)
        ->and($request->input('installment_price'))->toBeNull()
        ->and($request->input('installment_months'))->toBeNull();
});

it('stores a fixed installment and does not keep a later monthly price', function () {
    $request = choiceRequest([
        'billing_model' => 'one_time_or_installment',
        'price' => 49,
        'installment_price' => 15,
        'installment_months' => 6,
        'subscription_price' => 9,
        'choice_recurring_compare' => 10,
    ]);

    $request->validateResolved();

    expect($request->input('price'))->toEqual(49)
        ->and($request->input('installment_price'))->toEqual(15)
        ->and($request->input('installment_months'))->toBe(6)
        ->and($request->input('subscription_price'))->toBeNull()
        ->and($request->input('choice_recurring_compare'))->toEqual(10);
});

it('prices the selected card and applies a voucher only to that card', function () {
    $settings = new stdClass;
    $settings->fields = ['selling_tax' => 0];
    app()->instance('system_settings', $settings);

    $monthly = choiceCourse();
    $coupon = new CourseCoupon([
        'code' => 'SMARTBOT',
        'discount_type' => 'percentage',
        'discount' => 10,
    ]);
    $payments = app(PaymentService::class);

    expect(ChoiceBilling::chargeAmount($monthly, 'one_time'))->toBe(49.0)
        ->and(ChoiceBilling::chargeAmount($monthly, 'recurring'))->toBe(15.0)
        ->and(ChoiceBilling::plan(null))->toBeNull()
        ->and(ChoiceBilling::plan('yearly'))->toBeNull();

    $oneTime = $payments->calculateCustomPrice(ChoiceBilling::chargeAmount($monthly, 'one_time'), $coupon);
    $recurring = $payments->calculateCustomPrice(ChoiceBilling::chargeAmount($monthly, 'recurring'), $coupon);

    expect($oneTime['finalPrice'])->toBe(44.1)
        ->and($recurring['finalPrice'])->toBe(13.5)
        ->and($oneTime['couponDiscount'])->not->toBe($recurring['couponDiscount']);
});

it('ends a choice installment after the paid count and does not switch to another monthly price', function () {
    expect(ChoiceBilling::shouldFinishInstallment(5, 6))->toBeFalse()
        ->and(ChoiceBilling::shouldFinishInstallment(6, 6))->toBeTrue()
        ->and(ChoiceBilling::shouldFinishInstallment(7, 6))->toBeTrue();

    $course = choiceCourse([
        'billing_model' => CourseBillingModel::ONE_TIME_OR_INSTALLMENT,
        'installment_price' => 15,
        'installment_months' => 6,
        'subscription_price' => null,
        'stripe_ongoing_price_id' => null,
    ]);

    expect($course->usesInstallmentSubscription())->toBeFalse()
        ->and($course->usesSubscriptionBilling())->toBeFalse()
        ->and($course->usesOneTimeOrInstallment())->toBeTrue()
        ->and(ChoiceBilling::chargeAmount($course, 'recurring'))->toBe(15.0)
        ->and(ChoiceBilling::checkoutMode($course))->toBe('one_time_or_installment');
});

it('keeps one-time access, waits on a missed installment, and restores access when the plan is finished', function () {
    $service = app(SubscriptionAccessService::class);
    $user = choiceUser();
    $course = choiceCourse([
        'billing_model' => CourseBillingModel::ONE_TIME_OR_INSTALLMENT,
    ]);

    $oneTime = new CourseEnrollment([
        'access_status' => EnrollmentAccessStatus::ACTIVE,
    ]);
    $oneTime->setRelation('subscription', null);

    $pastDue = new CourseEnrollment([
        'access_status' => EnrollmentAccessStatus::SUSPENDED,
    ]);
    $pastDue->setRelation('subscription', new Subscription([
        'status' => SubscriptionStatus::PAST_DUE,
    ]));

    $finished = new CourseEnrollment([
        'access_status' => EnrollmentAccessStatus::ACTIVE,
    ]);
    $finished->setRelation('subscription', new Subscription([
        'status' => SubscriptionStatus::CANCELED,
        'choice_installment_completed_at' => now(),
    ]));

    expect($service->getAccessMode($user, $course, $oneTime))->toBe('full')
        ->and($service->getAccessMode($user, $course, $pastDue))->toBe('installment_locked')
        ->and($service->getAccessMode($user, $course, $finished))->toBe('full');

    $monthly = choiceCourse();
    $activeMonthly = new CourseEnrollment([
        'access_status' => EnrollmentAccessStatus::ACTIVE,
    ]);
    $canceledMonthly = new CourseEnrollment([
        'access_status' => EnrollmentAccessStatus::SUSPENDED,
    ]);

    expect($service->getAccessMode($user, $monthly, $activeMonthly))->toBe('full')
        ->and($service->getAccessMode($user, $monthly, $canceledMonthly))->toBe('completed_only');
});

it('does not treat an existing monthly course as a choice checkout', function () {
    $course = choiceCourse([
        'billing_model' => CourseBillingModel::SUBSCRIPTION,
    ]);

    expect($course->usesChoiceBilling())->toBeFalse()
        ->and($course->usesSubscriptionBilling())->toBeTrue()
        ->and(ChoiceBilling::checkoutMode($course))->toBeNull();
});
