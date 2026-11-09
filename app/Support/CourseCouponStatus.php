<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class CourseCouponStatus
{
    public const ORDER = ['active', 'scheduled', 'used_up', 'expired', 'inactive'];

    public const LABELS = [
        'active' => 'Active',
        'scheduled' => 'Scheduled',
        'used_up' => 'Used Up',
        'expired' => 'Expired',
        'inactive' => 'Inactive',
    ];

    /**
     * Same order as the coupon list: inactive, then expired, then scheduled, then used up.
     */
    public static function key(
        bool $isActive,
        mixed $validFrom,
        mixed $validTo,
        mixed $usageLimit,
        int $used,
        CarbonInterface|string|null $now = null,
    ): string {
        $now = $now instanceof CarbonInterface ? $now : Carbon::parse($now ?? now());

        if (! $isActive) {
            return 'inactive';
        }

        if ($validTo !== null && $validTo !== '' && Carbon::parse($validTo)->lt($now)) {
            return 'expired';
        }

        if ($validFrom !== null && $validFrom !== '' && Carbon::parse($validFrom)->gt($now)) {
            return 'scheduled';
        }

        if ($usageLimit !== null && $usageLimit !== '' && (int) $usageLimit > 0 && $used >= (int) $usageLimit) {
            return 'used_up';
        }

        return 'active';
    }
}
