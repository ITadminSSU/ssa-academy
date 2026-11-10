<?php

namespace App\Enums;

enum CourseBillingModel: string
{
    case ONE_TIME = 'one_time';
    case SUBSCRIPTION = 'subscription';
    case UPFRONT_SUBSCRIPTION = 'upfront_subscription';
    case PRE_REGISTER_SUBSCRIPTION = 'pre_register_subscription';
    case INSTALLMENT_SUBSCRIPTION = 'installment_subscription';
    case ONE_TIME_OR_MONTHLY = 'one_time_or_monthly';
    case ONE_TIME_OR_INSTALLMENT = 'one_time_or_installment';

    public function getLabel(): string
    {
        return match ($this) {
            self::ONE_TIME => 'One-time purchase',
            self::SUBSCRIPTION => 'Monthly subscription',
            self::UPFRONT_SUBSCRIPTION => 'Upfront + monthly subscription',
            self::PRE_REGISTER_SUBSCRIPTION => 'Pre-registration + monthly subscription',
            self::INSTALLMENT_SUBSCRIPTION => 'Installment, then monthly',
            self::ONE_TIME_OR_MONTHLY => 'One-time or monthly',
            self::ONE_TIME_OR_INSTALLMENT => 'One-time or installment',
        };
    }
}
