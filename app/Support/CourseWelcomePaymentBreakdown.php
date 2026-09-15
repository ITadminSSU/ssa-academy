<?php

namespace App\Support;

use App\Enums\CoursePricingType;
use App\Enums\PaymentBillingType;
use App\Models\Course\Course;
use App\Models\Course\CourseCoupon;
use App\Models\Course\CourseEnrollment;
use App\Services\Payment\LaunchOfferService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Modules\PaymentGateways\Models\PaymentHistory;

class CourseWelcomePaymentBreakdown
{
    public function __construct(
        private LaunchOfferService $launchOffer,
    ) {}

    /**
     * @param  list<PaymentHistory>|null  $payments
     * @return array{
     *     this_payment_amount: float,
     *     discount_amount: float,
     *     discount_percent: float,
     *     coupon_code: string,
     *     monthly_price: float,
     *     charges_first_month_now: bool,
     *     bullets: list<string>
     * }
     */
    public function build(CourseEnrollment $enrollment, ?array $payments = null): array
    {
        $course = $enrollment->course;
        $payments ??= $this->loadPayments($enrollment);
        $thisPayment = $this->completingPayment($enrollment, $payments);

        $depositAmount = (float) ($enrollment->deposit_amount ?? 0);
        $depositDate = $enrollment->deposit_paid_at;
        $recordedAmount = (float) ($thisPayment?->amount ?? 0);
        $thisPaymentDate = $enrollment->balance_paid_at
            ?? $thisPayment?->created_at
            ?? $enrollment->entry_date
            ?? now();

        $isPreRegBalance = $this->isPreRegBalance($enrollment, $thisPayment);
        $monthlyPrice = $course ? $this->monthlyPrice($course) : 0.0;
        $chargesFirstMonthNow = $this->chargesFirstMonthNow($course, $isPreRegBalance, $monthlyPrice);
        $coursePaidRecorded = $this->courseAmountPaid($thisPayment, $recordedAmount, $monthlyPrice, $chargesFirstMonthNow);

        $expectedSubtotal = $this->expectedSubtotalBeforeDiscount($enrollment, $thisPayment, $isPreRegBalance);
        $voucher = $this->resolveVoucher($enrollment, $payments, $thisPayment, $expectedSubtotal, $coursePaidRecorded);
        $discountAmount = $voucher['amount'];
        $discountPercent = $voucher['percent'];
        $couponCode = $voucher['code'];

        if ($discountAmount > 0 && $expectedSubtotal + 0.009 < $coursePaidRecorded + $discountAmount) {
            $expectedSubtotal = round($coursePaidRecorded + $discountAmount, 2);
        }

        $thisPaymentAmount = $coursePaidRecorded;
        if ($discountAmount > 0) {
            $afterDiscount = round(max(0, $expectedSubtotal - $discountAmount), 2);
            if ($thisPaymentAmount <= 0 || abs($thisPaymentAmount - $expectedSubtotal) < 0.009) {
                $thisPaymentAmount = $afterDiscount;
            }
        } elseif ($thisPaymentAmount <= 0 && $expectedSubtotal > 0) {
            $thisPaymentAmount = $expectedSubtotal;
        }

        $totalCoursePrice = $depositAmount + $expectedSubtotal;
        if ($totalCoursePrice <= 0) {
            $totalCoursePrice = $thisPaymentAmount + $discountAmount;
        }

        $monthlyBilledToday = $chargesFirstMonthNow ? $monthlyPrice : 0.0;
        $totalPaidToday = round($thisPaymentAmount + $monthlyBilledToday, 2);

        $bullets = [];

        if ($depositAmount > 0) {
            $bullets[] = 'Pre-registration ('.$this->date($depositDate).'): '.PaymentVoucherCopy::money($depositAmount);
        }

        $priceLabel = $depositAmount > 0 || $isPreRegBalance ? 'Balance' : 'Course Price';
        if ($expectedSubtotal > 0 && ($couponCode !== '' || $discountAmount > 0 || $monthlyPrice > 0)) {
            $bullets[] = $priceLabel.': '.PaymentVoucherCopy::money($expectedSubtotal);
        }

        if ($couponCode !== '' || $discountAmount > 0) {
            $bullets[] = PaymentVoucherCopy::breakdownLine($couponCode, $discountAmount, $discountPercent);
        }

        $bullets[] = 'This Payment ('.$this->date($thisPaymentDate).'): '.PaymentVoucherCopy::money($thisPaymentAmount);
        $bullets[] = 'Total Course Price: '.PaymentVoucherCopy::money($totalCoursePrice);

        if ($monthlyPrice > 0) {
            if ($isPreRegBalance) {
                $bullets[] = 'Project Plans Subscription: '.PaymentVoucherCopy::money($monthlyPrice).'/month after your free month';
            } elseif ($chargesFirstMonthNow) {
                $bullets[] = 'Project Plans (billed today): '.PaymentVoucherCopy::money($monthlyBilledToday);
                if ($monthlyBilledToday > 0 && abs($totalPaidToday - $thisPaymentAmount) > 0.009) {
                    $bullets[] = 'Total paid today: '.PaymentVoucherCopy::money($totalPaidToday);
                }
                $bullets[] = 'Project Plans Subscription: '.PaymentVoucherCopy::money($monthlyPrice).'/month starting next month';
            } else {
                $bullets[] = 'Project Plans Subscription: '.PaymentVoucherCopy::money($monthlyPrice).'/month';
            }
        }

        return [
            'this_payment_amount' => $thisPaymentAmount,
            'discount_amount' => $discountAmount,
            'discount_percent' => $discountPercent,
            'coupon_code' => $couponCode,
            'monthly_price' => $monthlyPrice,
            'charges_first_month_now' => $chargesFirstMonthNow,
            'bullets' => $bullets,
        ];
    }

    /**
     * @param  list<PaymentHistory>  $payments
     */
    public function completingPayment(CourseEnrollment $enrollment, ?array $payments = null): ?PaymentHistory
    {
        $payments ??= $this->loadPayments($enrollment);

        if ($enrollment->balance_payment_history_id) {
            foreach ($payments as $payment) {
                if ((int) $payment->id === (int) $enrollment->balance_payment_history_id) {
                    return $payment;
                }
            }
        }

        foreach ($payments as $payment) {
            $type = $this->billingTypeValue($payment);
            if ($type === PaymentBillingType::DEPOSIT->value || $type === PaymentBillingType::SUBSCRIPTION_RENEWAL->value) {
                continue;
            }

            return $payment;
        }

        return null;
    }

    public function shouldWaitForPayment(CourseEnrollment $enrollment): bool
    {
        $course = $enrollment->course;
        if (! $course) {
            return false;
        }

        $pricing = $course->pricing_type;
        $isPaid = $pricing === CoursePricingType::PAID || $pricing === CoursePricingType::PAID->value;
        if (! $isPaid) {
            return false;
        }

        return $this->completingPayment($enrollment) === null;
    }

    /**
     * @param  list<PaymentHistory>  $payments
     */
    private function isPreRegBalance(CourseEnrollment $enrollment, ?PaymentHistory $thisPayment): bool
    {
        if ($this->billingTypeValue($thisPayment) === PaymentBillingType::BALANCE->value) {
            return true;
        }

        return (float) ($enrollment->deposit_amount ?? 0) > 0 && filled($enrollment->balance_paid_at);
    }

    private function chargesFirstMonthNow(?Course $course, bool $isPreRegBalance, float $monthlyPrice): bool
    {
        if (! $course || $monthlyPrice <= 0 || $isPreRegBalance) {
            return false;
        }

        return $course->usesUpfrontSubscription()
            || $course->usesPreRegistrationSubscription();
    }

    private function monthlyPrice(Course $course): float
    {
        if (! $course->usesSubscriptionBilling()) {
            return 0.0;
        }

        return $this->launchOffer->subscriptionPrice($course);
    }

    private function courseAmountPaid(
        ?PaymentHistory $thisPayment,
        float $recordedAmount,
        float $monthlyPrice,
        bool $chargesFirstMonthNow,
    ): float {
        $charged = (float) data_get($thisPayment?->meta, 'charged_amount', 0);
        if ($charged > 0) {
            return round($charged, 2);
        }

        if ($chargesFirstMonthNow && $monthlyPrice > 0 && $recordedAmount + 0.009 >= $monthlyPrice) {
            $coursePortion = round($recordedAmount - $monthlyPrice, 2);

            return max(0, $coursePortion);
        }

        return round($recordedAmount, 2);
    }

    private function expectedSubtotalBeforeDiscount(
        CourseEnrollment $enrollment,
        ?PaymentHistory $thisPayment,
        bool $isPreRegBalance,
    ): float {
        $course = $enrollment->course;
        $depositAmount = (float) ($enrollment->deposit_amount ?? 0);

        if ($depositAmount > 0 || $isPreRegBalance || $this->billingTypeValue($thisPayment) === PaymentBillingType::BALANCE->value) {
            return (float) ($enrollment->balance_amount ?? ($course ? $this->launchOffer->balanceAmount($course) : 0));
        }

        if ($course?->usesPreRegistrationSubscription()) {
            return (float) ($course->launch_full_upfront_price ?? $this->launchOffer->fullUpfrontPrice($course));
        }

        $price = $course && $course->discount && $course->discount_price
            ? (float) $course->discount_price
            : (float) ($course->price ?? 0);

        return max(0, $price);
    }

    /**
     * @param  list<PaymentHistory>  $payments
     * @return array{code: string, amount: float, percent: float}
     */
    private function resolveVoucher(
        CourseEnrollment $enrollment,
        array $payments,
        ?PaymentHistory $thisPayment,
        float $expectedSubtotal,
        float $paidAmount,
    ): array {
        $couponCode = '';
        $discountAmount = 0.0;
        $discountPercent = 0.0;

        foreach ($payments as $payment) {
            $code = PaymentVoucherCopy::normalizeCode($payment->coupon)
                ?: PaymentVoucherCopy::normalizeCode(data_get($payment->meta, 'coupon_code'));

            $metaDiscount = (float) data_get($payment->meta, 'coupon_discount', 0);
            if ($metaDiscount > $discountAmount) {
                $discountAmount = $metaDiscount;
            }

            if ($code !== '') {
                $couponCode = $code;
                break;
            }
        }

        $coupon = null;
        if ($couponCode !== '' && \Illuminate\Support\Facades\Schema::hasTable('course_coupons')) {
            $coupon = CourseCoupon::query()->whereRaw('LOWER(code) = ?', [strtolower($couponCode)])->first();
        }

        if ($coupon && $this->couponIsPercentage($coupon)) {
            $discountPercent = (float) $coupon->discount;
        }

        $discountBase = $expectedSubtotal > 0 ? $expectedSubtotal : $paidAmount;

        if ($coupon && $discountAmount <= 0) {
            $discountAmount = $this->couponDiscountForSubtotal($coupon, $discountBase);
        }

        if ($discountAmount <= 0 && $expectedSubtotal > 0 && $paidAmount >= 0 && $expectedSubtotal > $paidAmount + 0.009) {
            $discountAmount = round($expectedSubtotal - $paidAmount, 2);
        }

        return [
            'code' => $couponCode,
            'amount' => $discountAmount,
            'percent' => $discountPercent,
        ];
    }

    private function couponIsPercentage(CourseCoupon $coupon): bool
    {
        return in_array(strtolower((string) $coupon->discount_type), ['percentage', 'percent', 'pct'], true);
    }

    private function couponDiscountForSubtotal(CourseCoupon $coupon, float $subtotal): float
    {
        $value = (float) $coupon->discount;

        if ($value <= 0) {
            return 0.0;
        }

        if ($this->couponIsPercentage($coupon)) {
            if ($subtotal <= 0) {
                return 0.0;
            }

            return min($subtotal, round(($subtotal * $value) / 100, 2));
        }

        if ($subtotal > 0) {
            return min($subtotal, round($value, 2));
        }

        return round($value, 2);
    }

    /**
     * @return list<PaymentHistory>
     */
    private function loadPayments(CourseEnrollment $enrollment): array
    {
        $payments = [];
        $seen = [];

        $add = function (?PaymentHistory $payment) use (&$payments, &$seen): void {
            if (! $payment || isset($seen[$payment->id])) {
                return;
            }

            $seen[$payment->id] = true;
            $payments[] = $payment;
        };

        if ($enrollment->balance_payment_history_id) {
            $add(PaymentHistory::query()->find($enrollment->balance_payment_history_id));
        }

        if ($enrollment->deposit_payment_history_id) {
            $add(PaymentHistory::query()->find($enrollment->deposit_payment_history_id));
        }

        PaymentHistory::query()
            ->where('user_id', $enrollment->user_id)
            ->where('purchase_type', Course::class)
            ->where('purchase_id', $enrollment->course_id)
            ->orderByDesc('id')
            ->get()
            ->each($add);

        return $payments;
    }

    private function billingTypeValue(?PaymentHistory $payment): string
    {
        if (! $payment) {
            return '';
        }

        $type = $payment->billing_type;

        return $type instanceof PaymentBillingType ? $type->value : (string) $type;
    }

    private function date(null|CarbonInterface|string $date): string
    {
        if (! $date) {
            return 'N/A';
        }

        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        return $date->timezone(config('app.timezone'))->format('F j, Y');
    }
}
