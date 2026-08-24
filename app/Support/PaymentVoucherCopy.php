<?php

namespace App\Support;

class PaymentVoucherCopy
{
    public static function normalizeCode(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }

    public static function money(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }

    public static function breakdownLine(?string $code, float $discount): string
    {
        $code = self::normalizeCode($code);

        if ($code !== '') {
            return 'Voucher '.$code.': -'.self::money($discount);
        }

        return 'Voucher: -'.self::money($discount);
    }

    public static function stripeLineName(string $baseName, ?string $code, float $discount = 0): string
    {
        $code = self::normalizeCode($code);
        $name = $baseName;

        if ($code !== '' && $discount > 0) {
            $name = $baseName.' (voucher '.$code.')';
        }

        if (mb_strlen($name) <= 250) {
            return $name;
        }

        return mb_substr($name, 0, 250);
    }

    public static function stripeLineDescription(?string $code, float $discount, float $originalAmount): ?string
    {
        $code = self::normalizeCode($code);

        if ($discount <= 0) {
            return null;
        }

        $applied = $code !== ''
            ? 'Voucher '.$code.' applied: -'.self::money($discount).'.'
            : 'Voucher applied: -'.self::money($discount).'.';

        if ($originalAmount > 0) {
            $applied .= ' Original amount '.self::money($originalAmount).'.';
        }

        return $applied;
    }
}
