<?php

namespace App\Enums;

enum EnrollmentAccessStatus: string
{
    case ACTIVE = 'active';
    case RESERVED = 'reserved';
    case CANCELED = 'canceled';
    case SUSPENDED = 'suspended';
    case EXPIRED = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::RESERVED => 'Reserved (deposit paid)',
            self::CANCELED => 'Canceled',
            self::SUSPENDED => 'Suspended',
            self::EXPIRED => 'Expired',
        };
    }

    public function grantsPlayerAccess(): bool
    {
        return match ($this) {
            self::ACTIVE => true,
            self::SUSPENDED => true, // may still grant via active subscription grace
            default => false,
        };
    }
}
