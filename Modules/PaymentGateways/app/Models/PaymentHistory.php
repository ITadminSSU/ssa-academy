<?php

namespace Modules\PaymentGateways\Models;

use App\Enums\PaymentBillingType;
use App\Enums\PaymentRefundStatus;
use App\Models\PaymentRefundAuditLog;
use App\Models\User;
use App\Models\Course\Course;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Exam\Models\Exam;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class PaymentHistory extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'user_id',
        'payment_type',
        'billing_type',
        'subscription_id',
        'tax',
        'coupon',
        'amount',
        'invoice',
        'admin_revenue',
        'instructor_revenue',
        'transaction_id',
        'session_id',
        'purchase_type',
        'purchase_id',
        'meta',
        'refund_status',
        'refund_notes',
    ];

    protected $casts = [
        'meta' => 'array',
        'refund_status' => PaymentRefundStatus::class,
        'billing_type' => PaymentBillingType::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function purchasable(): MorphTo
    {
        return $this->morphTo(null, 'purchase_type', 'purchase_id');
    }

    // Alias for purchasable relationship
    public function purchase(): MorphTo
    {
        return $this->morphTo(null, 'purchase_type', 'purchase_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'purchase_id')
            ->where('purchase_type', Course::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class, 'purchase_id')
            ->where('purchase_type', Exam::class);
    }

    public function refundAuditLogs(): HasMany
    {
        return $this->hasMany(PaymentRefundAuditLog::class);
    }

    public function subscription()
    {
        return $this->belongsTo(\App\Models\Subscription::class);
    }
}
