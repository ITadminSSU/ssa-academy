<?php

namespace App\Services\Payment;

use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class LaunchOfferService
{
    public function courseHasOfferColumns(): bool
    {
        return Schema::hasColumn('courses', 'launch_offer_enabled');
    }

    public function isConfigured(Course $course): bool
    {
        if (! $this->courseHasOfferColumns()) {
            return false;
        }

        return $course->usesPreRegistrationSubscription() || (bool) $course->launch_offer_enabled;
    }

    public function windowStart(Course $course): Carbon
    {
        $raw = $course->launch_offer_starts_at
            ?? config('payment.launch_offer.window_start');

        return Carbon::parse($raw);
    }

    public function windowEnd(Course $course): Carbon
    {
        $raw = $course->launch_offer_ends_at
            ?? config('payment.launch_offer.window_end');

        return Carbon::parse($raw);
    }

    public function isInPreRegisterWindow(Course $course, ?Carbon $at = null): bool
    {
        if (! $this->isConfigured($course)) {
            return false;
        }

        $at ??= now();

        return $at->betweenIncluded($this->windowStart($course), $this->windowEnd($course));
    }

    public function isFullPricePeriod(Course $course, ?Carbon $at = null): bool
    {
        if (! $this->isConfigured($course)) {
            return false;
        }

        $at ??= now();

        return $at->greaterThan($this->windowEnd($course));
    }

    public function listPrice(Course $course): float
    {
        return (float) ($course->launch_list_price
            ?? config('payment.launch_offer.list_price', 75));
    }

    public function offerPrice(Course $course): float
    {
        return (float) ($course->launch_offer_price
            ?? config('payment.launch_offer.offer_price', 70));
    }

    public function depositAmount(Course $course): float
    {
        return (float) ($course->launch_deposit_amount
            ?? config('payment.launch_offer.deposit_amount', 20));
    }

    public function balanceAmount(Course $course): float
    {
        return (float) ($course->launch_balance_amount
            ?? config('payment.launch_offer.balance_amount', 50));
    }

    public function fullUpfrontPrice(Course $course): float
    {
        return (float) ($course->launch_full_upfront_price
            ?? config('payment.launch_offer.full_upfront_price', 75));
    }

    public function subscriptionPrice(Course $course): float
    {
        if ($course->subscription_price !== null && (float) $course->subscription_price > 0) {
            return (float) $course->subscription_price;
        }

        return (float) config('payment.launch_offer.subscription_price', 6);
    }

    public function balanceGraceDays(Course $course): int
    {
        return (int) ($course->launch_balance_grace_days
            ?? config('payment.launch_offer.balance_grace_days', 5));
    }

    public function subscriptionTrialEndsAt(Course $course): Carbon
    {
        $raw = $course->launch_subscription_trial_ends_at
            ?? config('payment.launch_offer.subscription_trial_ends_at');

        return Carbon::parse($raw)->endOfDay();
    }

    /**
     * Full-upfront / no pre-reg: charge enrollment + first month now (no free month).
     * Pre-reg balance checkout keeps a separate free-month trial.
     */
    public const FULL_UPFRONT_CHARGE_FIRST_MONTH = true;

    /** Pre-reg on-time balance: one free month, then first recurring charge. */
    public const PRE_REGISTER_FREE_MONTH_DAYS = 30;

    /**
     * Pre-reg students who pay the launch balance on time get a tagged free month
     * (Stripe trial). Full-upfront students do not get a trial.
     */
    public function stripeTrialEndForBalanceCheckout(Course $course): Carbon
    {
        return now()->addDays(self::PRE_REGISTER_FREE_MONTH_DAYS);
    }

    public function balanceDueAt(Course $course): Carbon
    {
        if ($course->launch_at) {
            return $course->launch_at->copy();
        }

        return $this->windowEnd($course)->copy()->addDay();
    }

    public function balanceDeadlineAt(Course $course): Carbon
    {
        return $this->balanceDueAt($course)
            ->copy()
            ->addDays($this->balanceGraceDays($course))
            ->endOfDay();
    }

    public function allowsDepositCheckout(Course $course): bool
    {
        return $this->isInPreRegisterWindow($course)
            && $course->pricing_type === 'paid'
            && $this->depositAmount($course) > 0;
    }

    public function allowsFullLaunchCheckout(Course $course): bool
    {
        return $this->isFullPricePeriod($course)
            && $course->pricing_type === 'paid'
            && $this->fullUpfrontPrice($course) > 0;
    }

    /**
     * Frontend / API payload for course cards and checkout.
     */
    public function toFrontendPayload(Course $course, ?CourseEnrollment $enrollment = null): array
    {
        if (! $this->isConfigured($course)) {
            return [
                'enabled' => false,
                'phase' => 'none',
            ];
        }

        $phase = $this->isInPreRegisterWindow($course)
            ? 'pre_register'
            : ($this->isFullPricePeriod($course) ? 'full_price' : 'scheduled');

        $reserved = $enrollment
            && $enrollment->access_status?->value === 'reserved'
            && empty($enrollment->balance_paid_at);

        $balanceDueAt = $this->balanceDueAt($course);
        $balanceDeadlineAt = $this->balanceDeadlineAt($course);

        $balanceOpen = $reserved
            && now()->greaterThanOrEqualTo($balanceDueAt)
            && now()->lessThanOrEqualTo($balanceDeadlineAt);

        return [
            'enabled' => true,
            'phase' => $phase,
            'list_price' => $this->listPrice($course),
            'offer_price' => $this->offerPrice($course),
            'deposit_amount' => $this->depositAmount($course),
            'balance_amount' => $this->balanceAmount($course),
            'full_upfront_price' => $this->fullUpfrontPrice($course),
            'subscription_price' => $this->subscriptionPrice($course),
            'window_start' => $this->windowStart($course)->toIso8601String(),
            'window_end' => $this->windowEnd($course)->toIso8601String(),
            'balance_due_at' => $balanceDueAt->toIso8601String(),
            'balance_deadline_at' => $balanceDeadlineAt->toIso8601String(),
            'subscription_trial_ends_at' => $this->subscriptionTrialEndsAt($course)->toIso8601String(),
            'deposit_non_refundable' => (bool) config('payment.launch_offer.deposit_non_refundable', true),
            'grace_days' => $this->balanceGraceDays($course),
            'reserved_seat' => (bool) $reserved,
            'balance_payment_open' => (bool) $balanceOpen,
            'can_pre_register' => $phase === 'pre_register' && ! $reserved && ! ($enrollment?->hasFullAccess() ?? false),
            'can_pay_balance' => (bool) $balanceOpen,
            'can_full_enroll' => $phase === 'full_price' && ! ($enrollment?->hasFullAccess() ?? false) && ! $reserved,
        ];
    }
}
