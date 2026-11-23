<?php

use App\Enums\CourseBillingModel;
use App\Enums\PaymentBillingType;
use App\Enums\SubscriptionStatus;
use App\Models\Course\Course;
use App\Models\Subscription;
use App\Support\EnrollmentListBilling;
use Modules\PaymentGateways\Models\PaymentHistory;

test('coupon code prefers the coupon column then meta', function () {
    $fromColumn = new PaymentHistory(['coupon' => 'save10', 'meta' => []]);
    $fromMeta = new PaymentHistory(['coupon' => null, 'meta' => ['coupon_code' => 'launch50']]);
    $empty = new PaymentHistory(['coupon' => '', 'meta' => []]);

    expect(EnrollmentListBilling::couponCodeFromPayment($fromColumn))->toBe('SAVE10')
        ->and(EnrollmentListBilling::couponCodeFromPayment($fromMeta))->toBe('LAUNCH50')
        ->and(EnrollmentListBilling::couponCodeFromPayment($empty))->toBe('')
        ->and(EnrollmentListBilling::couponCodeFromPayment(null))->toBe('');
});

test('payment type follows the creating charge', function () {
    $choice = new Course([
        'billing_model' => CourseBillingModel::ONE_TIME_OR_INSTALLMENT,
        'installment_months' => 6,
    ]);
    $installmentPlan = new Course([
        'billing_model' => CourseBillingModel::INSTALLMENT_SUBSCRIPTION,
        'installment_months' => 6,
    ]);
    $oneTimeCourse = new Course(['billing_model' => CourseBillingModel::ONE_TIME]);

    $oneTime = new PaymentHistory(['billing_type' => PaymentBillingType::ONE_TIME, 'coupon' => '3G82X560']);
    $installment = new PaymentHistory(['billing_type' => PaymentBillingType::SUBSCRIPTION]);
    $renewal = new PaymentHistory(['billing_type' => PaymentBillingType::SUBSCRIPTION_RENEWAL]);

    expect(EnrollmentListBilling::paymentTypeLabel($oneTimeCourse, $oneTime))->toBe('One-time')
        ->and(EnrollmentListBilling::paymentTypeLabel($choice, $oneTime))->toBe('One-time')
        ->and(EnrollmentListBilling::paymentTypeLabel($choice, $installment))->toBe('Installment')
        ->and(EnrollmentListBilling::paymentTypeLabel($installmentPlan, $installment))->toBe('Installment')
        ->and(EnrollmentListBilling::paymentTypeLabel($choice, $renewal))->toBe('')
        ->and(EnrollmentListBilling::paymentTypeLabel($choice, null))->toBe('')
        ->and(EnrollmentListBilling::subscriptionSummary(null, $choice)['label'])->toBe('Not a subscription');
});

test('one-time courses are labeled as not a subscription', function () {
    $course = new Course(['billing_model' => CourseBillingModel::ONE_TIME]);

    expect(EnrollmentListBilling::subscriptionSummary(null, $course))->toMatchArray([
        'status' => null,
        'label' => 'Not a subscription',
        'expires_at' => null,
    ]);
});

test('subscription courses without a stripe row show none', function () {
    $course = new Course(['billing_model' => CourseBillingModel::SUBSCRIPTION]);

    expect(EnrollmentListBilling::subscriptionSummary(null, $course)['label'])->toBe('None');
});

test('active subscriptions expose period end as expiry', function () {
    $course = new Course(['billing_model' => CourseBillingModel::SUBSCRIPTION]);
    $ends = now()->addMonth()->startOfSecond();
    $subscription = new Subscription([
        'status' => SubscriptionStatus::ACTIVE,
        'current_period_end' => $ends,
    ]);

    $summary = EnrollmentListBilling::subscriptionSummary($subscription, $course);

    expect($summary['status'])->toBe('active')
        ->and($summary['label'])->toBe('Active')
        ->and($summary['expires_at'])->toBe($ends->toIso8601String());
});

test('past due subscriptions use the grace end when present', function () {
    $course = new Course(['billing_model' => CourseBillingModel::UPFRONT_SUBSCRIPTION]);
    $periodEnd = now()->addDays(3);
    $graceEnd = now()->addDays(10);
    $subscription = new Subscription([
        'status' => SubscriptionStatus::PAST_DUE,
        'current_period_end' => $periodEnd,
        'grace_ends_at' => $graceEnd,
    ]);

    $summary = EnrollmentListBilling::subscriptionSummary($subscription, $course);

    expect($summary['label'])->toBe('Past due')
        ->and($summary['expires_at'])->toBe($graceEnd->toIso8601String());
});
