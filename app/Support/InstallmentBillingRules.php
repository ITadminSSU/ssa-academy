<?php

namespace App\Support;

use App\Enums\SubscriptionStatus;

class InstallmentBillingRules
{
    public static function planLabel(float $installment, int $months, float $ongoing, string $symbol = '$'): string
    {
        $monthWord = $months === 1 ? 'month' : 'months';

        return self::money($installment, $symbol).'/mo for '.$months.' '.$monthWord.', then '.self::money($ongoing, $symbol).'/mo';
    }

    public static function isInstallmentPrice(
        string $invoicePriceId,
        string $courseInstallmentPriceId,
        string $subscriptionPriceId,
        string $ongoingPriceId,
    ): bool {
        if ($invoicePriceId === '') {
            return false;
        }

        if ($ongoingPriceId !== '' && $invoicePriceId === $ongoingPriceId) {
            return false;
        }

        return $invoicePriceId === $courseInstallmentPriceId || $invoicePriceId === $subscriptionPriceId;
    }

    public static function shouldSwitchToOngoing(
        int $paidCount,
        int $months,
        bool $subscriptionActive,
        bool $hasOpenInvoice,
        string $subscriptionPriceId,
        string $ongoingPriceId,
    ): bool {
        if ($months < 1 || $paidCount < $months) {
            return false;
        }

        if (! $subscriptionActive || $hasOpenInvoice || $ongoingPriceId === '') {
            return false;
        }

        return $subscriptionPriceId !== $ongoingPriceId;
    }

    public static function accessMode(?SubscriptionStatus $status): string
    {
        if ($status === SubscriptionStatus::ACTIVE || $status === SubscriptionStatus::TRIALING) {
            return 'full';
        }

        return 'installment_locked';
    }

    private static function money(float $amount, string $symbol): string
    {
        $rounded = round($amount, 2);
        $formatted = abs($rounded - round($rounded)) < 0.001
            ? (string) (int) round($rounded)
            : number_format($rounded, 2, '.', '');

        return $symbol.$formatted;
    }
}
