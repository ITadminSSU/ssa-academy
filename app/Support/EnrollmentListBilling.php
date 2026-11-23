<?php

namespace App\Support;

use App\Enums\PaymentBillingType;
use App\Enums\SubscriptionStatus;
use App\Models\Course\Course;
use App\Models\Subscription;
use Modules\PaymentGateways\Models\PaymentHistory;

class EnrollmentListBilling
{
    public static function couponCodeFromPayment(?PaymentHistory $payment): string
    {
        if (! $payment) {
            return '';
        }

        $code = PaymentVoucherCopy::normalizeCode($payment->coupon)
            ?: PaymentVoucherCopy::normalizeCode(data_get($payment->meta, 'coupon_code'));

        return $code;
    }

    /**
     * Label for the charge that created the enrollment. Empty when that charge
     * is missing or is not a one-time payment or an installment plan.
     */
    public static function paymentTypeLabel(?Course $course, ?PaymentHistory $payment): string
    {
        if (! $payment) {
            return '';
        }

        $billing = $payment->billing_type;
        $value = $billing instanceof PaymentBillingType ? $billing->value : (string) $billing;

        if ($value === PaymentBillingType::ONE_TIME->value) {
            return 'One-time';
        }

        $installmentCourse = $course?->usesOneTimeOrInstallment() || $course?->usesInstallmentSubscription();

        if ($value === PaymentBillingType::SUBSCRIPTION->value && $installmentCourse) {
            return 'Installment';
        }

        return '';
    }

    /**
     * @return array{status: string|null, label: string, expires_at: string|null}
     */
    public static function subscriptionSummary(?Subscription $subscription, ?Course $course): array
    {
        if (! $course || ! $course->usesSubscriptionBilling()) {
            return [
                'status' => null,
                'label' => 'Not a subscription',
                'expires_at' => null,
            ];
        }

        if (! $subscription) {
            return [
                'status' => null,
                'label' => 'None',
                'expires_at' => null,
            ];
        }

        $status = $subscription->status instanceof SubscriptionStatus
            ? $subscription->status
            : SubscriptionStatus::tryFrom((string) $subscription->status);

        $expiresAt = $subscription->current_period_end;

        if ($status === SubscriptionStatus::PAST_DUE && $subscription->grace_ends_at) {
            $expiresAt = $subscription->grace_ends_at;
        }

        return [
            'status' => $status?->value,
            'label' => $status?->listLabel() ?? 'None',
            'expires_at' => $expiresAt?->toIso8601String(),
        ];
    }
}
