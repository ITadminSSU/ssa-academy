<?php

namespace App\Models\Course;

use App\Enums\EnrollmentAccessStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'enrollment_type',
        'access_status',
        'entry_date',
        'expiry_date',
        'suspended_at',
        'subscription_id',
        'deposit_amount',
        'deposit_paid_at',
        'deposit_payment_history_id',
        'balance_amount',
        'balance_due_at',
        'balance_deadline_at',
        'balance_paid_at',
        'balance_payment_history_id',
        'forfeited_at',
        'launch_offer_cohort',
        'deposit_confirmation_sent_at',
        'balance_due_notice_sent_at',
        'balance_mid_reminder_sent_at',
        'balance_final_reminder_sent_at',
        'balance_paid_confirmation_sent_at',
        'forfeit_notice_sent_at',
    ];

    protected $casts = [
        'access_status' => EnrollmentAccessStatus::class,
        'entry_date' => 'datetime',
        'expiry_date' => 'datetime',
        'suspended_at' => 'datetime',
        'deposit_amount' => 'decimal:2',
        'deposit_paid_at' => 'datetime',
        'balance_amount' => 'decimal:2',
        'balance_due_at' => 'datetime',
        'balance_deadline_at' => 'datetime',
        'balance_paid_at' => 'datetime',
        'forfeited_at' => 'datetime',
        'deposit_confirmation_sent_at' => 'datetime',
        'balance_due_notice_sent_at' => 'datetime',
        'balance_mid_reminder_sent_at' => 'datetime',
        'balance_final_reminder_sent_at' => 'datetime',
        'balance_paid_confirmation_sent_at' => 'datetime',
        'forfeit_notice_sent_at' => 'datetime',
    ];


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function hasFullAccess(): bool
    {
        if ($this->access_status === EnrollmentAccessStatus::ACTIVE) {
            return true;
        }

        if (
            $this->access_status === EnrollmentAccessStatus::SUSPENDED
            && $this->subscription
            && $this->subscription->grantsFullAccess()
        ) {
            return true;
        }

        return false;
    }

    public function isReservedSeat(): bool
    {
        return $this->access_status === EnrollmentAccessStatus::RESERVED
            && empty($this->balance_paid_at)
            && empty($this->forfeited_at);
    }

    public function isCanceled(): bool
    {
        return $this->access_status === EnrollmentAccessStatus::CANCELED;
    }

    public function isSuspended(): bool
    {
        return $this->access_status === EnrollmentAccessStatus::SUSPENDED;
    }
}
