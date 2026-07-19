<?php

namespace App\Enums;

enum CourseBillingModel: string
{
    case ONE_TIME = 'one_time';
    case SUBSCRIPTION = 'subscription';

    public function getLabel(): string
    {
        return match ($this) {
            self::ONE_TIME => 'One-time purchase',
            self::SUBSCRIPTION => 'Monthly subscription',
        };
    }
}
