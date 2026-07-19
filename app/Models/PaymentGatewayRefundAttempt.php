<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\PaymentGateways\Models\PaymentHistory;

class PaymentGatewayRefundAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payment_history_id',
        'initiated_by_user_id',
        'gateway',
        'transaction_id',
        'success',
        'gateway_refund_id',
        'response_payload',
        'error_message',
        'created_at',
    ];

    protected $casts = [
        'success' => 'boolean',
        'response_payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function paymentHistory(): BelongsTo
    {
        return $this->belongsTo(PaymentHistory::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }
}
