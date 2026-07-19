<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\PaymentGateways\Models\PaymentHistory;

class PaymentRefundAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payment_history_id',
        'changed_by_user_id',
        'previous_status',
        'new_status',
        'previous_notes',
        'new_notes',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function paymentHistory(): BelongsTo
    {
        return $this->belongsTo(PaymentHistory::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
