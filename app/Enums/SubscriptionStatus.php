<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case TRIALING = 'trialing';
    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case CANCELED = 'canceled';
    case UNPAID = 'unpaid';
    case PAUSED = 'paused';

    public function grantsFullAccess(): bool
    {
        return in_array($this, [self::TRIALING, self::ACTIVE], true);
    }

    public function isGraceEligible(): bool
    {
        return $this === self::PAST_DUE;
    }

    public function listLabel(): string
    {
        return match ($this) {
            self::TRIALING, self::ACTIVE => 'Active',
            self::PAST_DUE => 'Past due',
            self::CANCELED => 'Canceled',
            self::UNPAID => 'Unpaid',
            self::PAUSED => 'Paused',
        };
    }
}
