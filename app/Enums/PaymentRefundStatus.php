<?php

namespace App\Enums;

enum PaymentRefundStatus: string
{
    case PAID = 'paid';
    case REFUND_PENDING = 'refund_pending';
    case REFUNDED = 'refunded';

    public function getLabel(): string
    {
        return match ($this) {
            self::PAID => 'Paid',
            self::REFUND_PENDING => 'Refund Pending',
            self::REFUNDED => 'Refunded',
        };
    }
}
