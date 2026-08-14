<?php

namespace App\Enums;

enum PaymentBillingType: string
{
    case ONE_TIME = 'one_time';
    case SUBSCRIPTION = 'subscription';
    case SUBSCRIPTION_RENEWAL = 'subscription_renewal';
    case DEPOSIT = 'deposit';
    case BALANCE = 'balance';

    public function getLabel(): string
    {
        return match ($this) {
            self::ONE_TIME => 'One-time',
            self::SUBSCRIPTION => 'Subscription',
            self::SUBSCRIPTION_RENEWAL => 'Subscription renewal',
            self::DEPOSIT => 'Pre-register deposit',
            self::BALANCE => 'Launch balance',
        };
    }
}
