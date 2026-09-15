<?php

use App\Enums\CourseBillingModel;
use App\Enums\EnrollmentAccessStatus;
use App\Enums\PaymentBillingType;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Services\Payment\LaunchOfferService;
use App\Support\CourseWelcomePaymentBreakdown;
use Modules\PaymentGateways\Models\PaymentHistory;

function welcomeCourse(array $overrides = []): Course
{
    return new Course(array_merge([
        'title' => 'TEST COURSE ONLY (DO NOT REGISTER)',
        'pricing_type' => 'paid',
        'billing_model' => CourseBillingModel::UPFRONT_SUBSCRIPTION,
        'price' => 99,
        'subscription_price' => 1,
        'launch_offer_enabled' => false,
    ], $overrides));
}

function welcomeEnrollment(Course $course, array $overrides = []): CourseEnrollment
{
    $enrollment = new CourseEnrollment(array_merge([
        'user_id' => 11,
        'course_id' => 5,
        'access_status' => EnrollmentAccessStatus::ACTIVE,
        'entry_date' => '2026-09-15 07:31:00',
    ], $overrides));
    $enrollment->setRelation('course', $course);

    return $enrollment;
}

function welcomePayment(array $overrides = []): PaymentHistory
{
    $payment = new PaymentHistory(array_merge([
        'id' => 101,
        'user_id' => 11,
        'purchase_type' => Course::class,
        'purchase_id' => 5,
        'amount' => 6,
        'coupon' => 'TEST99',
        'billing_type' => PaymentBillingType::SUBSCRIPTION,
        'meta' => [
            'coupon_code' => 'TEST99',
            'coupon_discount' => 94,
            'charged_amount' => 5,
        ],
        'created_at' => '2026-09-15 07:31:00',
    ], $overrides));

    return $payment;
}

it('shows the voucher and monthly price on an upfront plus monthly welcome breakdown', function () {
    $course = welcomeCourse();
    $enrollment = welcomeEnrollment($course);
    $breakdown = app(CourseWelcomePaymentBreakdown::class)->build($enrollment, [welcomePayment()]);

    expect($breakdown['coupon_code'])->toBe('TEST99')
        ->and($breakdown['discount_amount'])->toBe(94.0)
        ->and($breakdown['this_payment_amount'])->toBe(5.0)
        ->and($breakdown['monthly_price'])->toBe(1.0)
        ->and($breakdown['charges_first_month_now'])->toBeTrue()
        ->and($breakdown['bullets'])->toContain('Course Price: $99.00')
        ->and($breakdown['bullets'])->toContain('Voucher TEST99: -$94.00')
        ->and($breakdown['bullets'])->toContain('This Payment (September 15, 2026): $5.00')
        ->and($breakdown['bullets'])->toContain('Project Plans (billed today): $1.00')
        ->and($breakdown['bullets'])->toContain('Project Plans Subscription: $1.00/month starting next month');
});

it('shows monthly after the free month when a pre-reg balance is paid', function () {
    $course = welcomeCourse([
        'billing_model' => CourseBillingModel::PRE_REGISTER_SUBSCRIPTION,
        'launch_offer_enabled' => true,
        'launch_balance_amount' => 79,
        'subscription_price' => 1,
    ]);
    $enrollment = welcomeEnrollment($course, [
        'deposit_amount' => 20,
        'deposit_paid_at' => '2026-08-15 00:00:00',
        'balance_amount' => 79,
        'balance_paid_at' => '2026-09-15 07:31:00',
        'balance_payment_history_id' => 101,
    ]);
    $payment = welcomePayment([
        'amount' => 50,
        'billing_type' => PaymentBillingType::BALANCE,
        'meta' => [
            'coupon_code' => 'SAVE29',
            'coupon_discount' => 29,
            'charged_amount' => 50,
        ],
        'coupon' => 'SAVE29',
    ]);

    $breakdown = app(CourseWelcomePaymentBreakdown::class)->build($enrollment, [$payment]);

    expect($breakdown['coupon_code'])->toBe('SAVE29')
        ->and($breakdown['charges_first_month_now'])->toBeFalse()
        ->and($breakdown['bullets'])->toContain('Pre-registration (August 15, 2026): $20.00')
        ->and($breakdown['bullets'])->toContain('Balance: $79.00')
        ->and($breakdown['bullets'])->toContain('Voucher SAVE29: -$29.00')
        ->and($breakdown['bullets'])->toContain('Project Plans Subscription: $1.00/month after your free month');
});

it('shows monthly on a full-upfront purchase after pre-registration has ended', function () {
    $course = welcomeCourse([
        'billing_model' => CourseBillingModel::PRE_REGISTER_SUBSCRIPTION,
        'launch_offer_enabled' => true,
        'launch_full_upfront_price' => 99,
        'subscription_price' => 1,
        'price' => null,
    ]);
    $enrollment = welcomeEnrollment($course);
    $payment = welcomePayment([
        'amount' => 6,
        'coupon' => 'TEST99',
        'meta' => [
            'coupon_code' => 'TEST99',
            'coupon_discount' => 94,
            'charged_amount' => 5,
        ],
    ]);

    $breakdown = app(CourseWelcomePaymentBreakdown::class)->build($enrollment, [$payment]);

    expect($breakdown['charges_first_month_now'])->toBeTrue()
        ->and($breakdown['bullets'])->toContain('Course Price: $99.00')
        ->and($breakdown['bullets'])->toContain('Voucher TEST99: -$94.00')
        ->and($breakdown['bullets'])->toContain('Project Plans Subscription: $1.00/month starting next month');
});

it('splits an invoice that bundled the first monthly charge when charged_amount is missing', function () {
    $course = welcomeCourse(['price' => 99, 'subscription_price' => 1]);
    $enrollment = welcomeEnrollment($course);
    $payment = welcomePayment([
        'amount' => 6,
        'coupon' => 'TEST99',
        'meta' => [
            'coupon_code' => 'TEST99',
            'coupon_discount' => 94,
        ],
    ]);

    $breakdown = app(CourseWelcomePaymentBreakdown::class)->build($enrollment, [$payment]);

    expect($breakdown['this_payment_amount'])->toBe(5.0)
        ->and($breakdown['bullets'])->toContain('This Payment (September 15, 2026): $5.00')
        ->and($breakdown['bullets'])->toContain('Total paid today: $6.00');
});
