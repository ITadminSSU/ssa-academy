<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Enums\CandidateStatus;
use App\Models\Course\CourseEnrollment;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Exam\Models\ExamEnrollment;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use App\Enums\LearnerUserType;
use App\Notifications\VerifyEmailNotification;

class User extends Authenticatable implements HasMedia, MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'user_type',
        'status',
        'photo',
        'google_id',
        'stripe_customer_id',
        'social_links',
        'email_verified_at',
        'instructor_id',
        'professional_type_id',
        'professional_type_other',
        'candidate_status',
        'candidate_notes',
        'candidate_status_updated_at',
        'legal_agreement_accepted_at',
        'legal_agreement_version',
        'legal_agreement_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'social_links' => 'array',
        'status' => 'integer',
        'user_type' => LearnerUserType::class,
        'candidate_status' => CandidateStatus::class,
        'candidate_status_updated_at' => 'datetime',
        'legal_agreement_accepted_at' => 'datetime',
    ];

    protected function photo(): Attribute
    {
        return Attribute::make(get: fn (?string $value) => public_asset_url($value));
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (
                $user->role === 'student'
                && $user->user_type === LearnerUserType::EXTERNAL
                && empty($user->candidate_status)
            ) {
                $user->candidate_status = CandidateStatus::NEW;
            }
        });
    }

    public function isEmployeeLearner(): bool
    {
        return $this->user_type === LearnerUserType::EMPLOYEE;
    }

    public function qualifiesForFreeCourseAccess(): bool
    {
        return $this->isEmployeeLearner();
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function professionalType(): BelongsTo
    {
        return $this->belongsTo(ProfessionalType::class);
    }

    public function courseEnrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function examEnrollments(): HasMany
    {
        return $this->hasMany(ExamEnrollment::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cv_resume')
            ->singleFile()
            ->acceptsMimeTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailNotification);
    }
}
