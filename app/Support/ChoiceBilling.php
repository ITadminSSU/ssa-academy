<?php

namespace App\Support;

use App\Enums\CourseBillingModel;
use App\Models\Course\Course;

class ChoiceBilling
{
    public const PLAN_ONE_TIME = 'one_time';

    public const PLAN_RECURRING = 'recurring';

    public static function checkoutMode(Course $course): ?string
    {
        if ($course->usesOneTimeOrMonthly()) {
            return CourseBillingModel::ONE_TIME_OR_MONTHLY->value;
        }

        if ($course->usesOneTimeOrInstallment()) {
            return CourseBillingModel::ONE_TIME_OR_INSTALLMENT->value;
        }

        return null;
    }

    public static function isChoiceMode(?string $mode): bool
    {
        return in_array($mode, [
            CourseBillingModel::ONE_TIME_OR_MONTHLY->value,
            CourseBillingModel::ONE_TIME_OR_INSTALLMENT->value,
        ], true);
    }

    public static function plan(?string $plan): ?string
    {
        return in_array($plan, [self::PLAN_ONE_TIME, self::PLAN_RECURRING], true) ? $plan : null;
    }

    public static function selectedPlan(?string $plan): string
    {
        return self::plan($plan) ?? self::PLAN_ONE_TIME;
    }

    public static function acceptsCoupon(string $plan): bool
    {
        return $plan === self::PLAN_ONE_TIME;
    }

    public static function chargeAmount(Course $course, string $plan): ?float
    {
        if (! self::plan($plan)) {
            return null;
        }

        $amount = $plan === self::PLAN_ONE_TIME
            ? (float) ($course->price ?? 0)
            : ($course->usesOneTimeOrInstallment()
                ? (float) ($course->installment_price ?? 0)
                : (float) ($course->subscription_price ?? 0));

        return $amount >= 1 ? round($amount, 2) : null;
    }

    public static function shouldFinishInstallment(int $paidCount, int $months): bool
    {
        return $months >= 1 && $paidCount >= $months;
    }
}
