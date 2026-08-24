<?php

namespace App\Models\Course;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\PaymentGateways\Models\PaymentHistory;

class CourseCoupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'course_id',
        'code',
        'discount_type',
        'discount',
        'valid_from',
        'valid_to',
        'usage_type',
        'usage_limit',
        'used_count',
        'is_active',
    ];

    protected $casts = [
        'discount' => 'decimal:2',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
        'is_active' => 'boolean',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PaymentHistory::class, 'coupon', 'code');
    }

    /**
     * Payments that redeemed this coupon (code on the payment row or in meta).
     */
    public function usageRecords()
    {
        $code = strtolower((string) $this->code);

        return PaymentHistory::query()
            ->where(function ($query) use ($code) {
                $query->whereRaw('LOWER(coupon) = ?', [$code])
                    ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(meta, "$.coupon_code"))) = ?', [$code]);
            });
    }

    /**
     * Query scope to filter only valid coupons
     * This is used in queries: CourseCoupon::isValid()->get()
     */
    public function scopeIsValid($query, $courseId = null, int|string|null $userId = null)
    {
        $now = now();

        $query = $query->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', $now);
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                    ->orWhereRaw('used_count < usage_limit');
            });

        // Check course applicability
        if ($courseId) {
            $query->where(function ($q) use ($courseId) {
                $q->whereNull('course_id')
                    ->orWhere('course_id', $courseId);
            });
        }

        if ($userId !== null) {
            $query->whereNotExists(function ($q) use ($userId) {
                $couponsTable = $q->getConnection()->getTablePrefix().'course_coupons';

                $q->selectRaw('1')
                    ->from('payment_histories')
                    ->where('user_id', $userId)
                    ->where(function ($inner) use ($couponsTable) {
                        $inner->whereRaw('LOWER(coupon) = LOWER(`'.$couponsTable.'`.`code`)')
                            ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(meta, "$.coupon_code"))) = LOWER(`'.$couponsTable.'`.`code`)');
                    });
            });
        }

        return $query;
    }

    public function isRedeemedByUser(int|string $userId): bool
    {
        return PaymentHistory::query()
            ->where('user_id', $userId)
            ->whereRaw('LOWER(coupon) = ?', [strtolower((string) $this->code)])
            ->exists();
    }

    /**
     * Check if this specific coupon instance is valid
     * This is used on model instances: $coupon->isValid()
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->valid_from && $now->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_to && $now->gt($this->valid_to)) {
            return false;
        }

        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function isValidForUser(int|string|null $userId, ?string $courseId = null): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        if ($courseId !== null && $courseId !== '') {
            if ($this->course_id && (string) $this->course_id !== (string) $courseId) {
                return false;
            }
        }

        if ($userId !== null && $this->isRedeemedByUser($userId)) {
            return false;
        }

        return true;
    }
}
