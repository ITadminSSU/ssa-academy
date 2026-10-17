<?php

namespace App\Models\Course;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
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
        'show_on_catalog',
        'assigned_email',
    ];

    protected $casts = [
        'discount' => 'decimal:2',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
        'is_active' => 'boolean',
        'show_on_catalog' => 'boolean',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedEmailRecords(): HasMany
    {
        return $this->hasMany(CourseCouponAssignedEmail::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (CourseCoupon $coupon) {
            if (! self::assignedEmailTableAvailable()) {
                return;
            }

            try {
                $coupon->assignedEmailRecords()->delete();
            } catch (\Throwable) {
                // Checkout and coupon deletion still proceed from the coupon row.
            }
        });
    }

    public static function assignedEmailTableAvailable(): bool
    {
        try {
            return Schema::hasTable('course_coupon_assigned_emails');
        } catch (\Throwable) {
            return false;
        }
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

        return $this->allowsCheckoutBy($userId);
    }

    public function assignedEmail(): ?string
    {
        $email = strtolower(trim((string) ($this->assigned_email ?? '')));

        return $email === '' ? null : $email;
    }

    public function matchesAssignedEmail(?string $email): bool
    {
        $allowed = $this->checkoutEmailList();

        if ($allowed === []) {
            return true;
        }

        return in_array(strtolower(trim((string) $email)), $allowed, true);
    }

    /**
     * Emails stored for this coupon. Null means the list table could not be read.
     *
     * @return list<string>|null
     */
    public function assignedEmailList(): ?array
    {
        if (! self::assignedEmailTableAvailable()) {
            return null;
        }

        try {
            $records = $this->relationLoaded('assignedEmailRecords')
                ? $this->assignedEmailRecords
                : $this->assignedEmailRecords()->get(['email']);

            return $records
                ->pluck('email')
                ->map(fn ($email) => strtolower(trim((string) $email)))
                ->filter(fn ($email) => $email !== '')
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Emails allowed to redeem this code. An empty list means any student.
     *
     * @return list<string>
     */
    public function checkoutEmailList(): array
    {
        $list = $this->assignedEmailList();

        if ($list === null || $list === []) {
            $legacy = $this->assignedEmail();

            return $legacy ? [$legacy] : [];
        }

        return $list;
    }

    /**
     * @param  list<string>  $emails
     */
    public function replaceAssignedEmails(array $emails): void
    {
        if (! self::assignedEmailTableAvailable()) {
            return;
        }

        $emails = array_values(array_unique(array_filter(array_map(
            fn ($email) => strtolower(trim((string) $email)),
            $emails,
        ))));

        $this->assignedEmailRecords()->delete();

        foreach ($emails as $email) {
            $this->assignedEmailRecords()->create([
                'email' => $email,
            ]);
        }
    }

    public function presentAssignedEmails(): void
    {
        $list = $this->assignedEmailList();

        if ($list === null || ($list === [] && $this->assignedEmail())) {
            $legacy = $this->assignedEmail();
            $emails = $legacy ? [$legacy] : [];
        } else {
            $emails = $list;
        }

        $this->setAttribute('assigned_emails', $emails);
        $this->syncOriginalAttribute('assigned_emails');
        $this->makeHidden('assignedEmailRecords');
    }

    public function allowsCheckoutBy(int|string|null $userId): bool
    {
        if ($this->checkoutEmailList() === []) {
            return true;
        }

        if ($userId === null) {
            return false;
        }

        $email = User::query()->whereKey($userId)->value('email');

        return $this->matchesAssignedEmail(is_string($email) ? $email : null);
    }

    public function scopeWithoutAssignedEmail($query)
    {
        try {
            $hasColumn = Schema::hasColumn($this->getTable(), 'assigned_email');
            $hasList = self::assignedEmailTableAvailable();
        } catch (\Throwable) {
            return $query;
        }

        if (! $hasColumn && ! $hasList) {
            return $query;
        }

        if ($hasColumn) {
            $query->where(function ($inner) {
                $inner->whereNull('assigned_email')
                    ->orWhereRaw('TRIM(assigned_email) = ?', ['']);
            });
        }

        if ($hasList) {
            $query->whereNotExists(function ($inner) {
                $inner->selectRaw('1')
                    ->from('course_coupon_assigned_emails')
                    ->whereColumn('course_coupon_assigned_emails.course_coupon_id', $this->getTable().'.id');
            });
        }

        return $query;
    }
}
