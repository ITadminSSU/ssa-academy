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
    ];

    protected $casts = [
        'access_status' => EnrollmentAccessStatus::class,
        'entry_date' => 'datetime',
        'expiry_date' => 'datetime',
        'suspended_at' => 'datetime',
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

    public function isSuspended(): bool
    {
        return $this->access_status === EnrollmentAccessStatus::SUSPENDED;
    }
}
