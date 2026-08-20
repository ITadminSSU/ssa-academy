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
use App\Support\MasterAdmin;

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
        'can_manage_platform_settings',
        'user_type',
        'status',
        'photo',
        'google_id',
        'stripe_customer_id',
        'social_links',
        'email_verified_at',
        'dashboard_first_visited_at',
        'instructor_id',
        'professional_type_id',
        'professional_type_other',
        'estimating_software',
        'estimating_software_other',
        'construction_experience',
        'worked_as_construction_va',
        'referred_by',
        'referrer_is_employee',
        'candidate_status',
        'candidate_notes',
        'candidate_status_updated_at',
        'legal_agreement_accepted_at',
        'legal_agreement_version',
        'legal_agreement_ip',
        'legal_confirmation_email_sent_at',
        'legal_confirmation_email_last_error',
        'signwell_document_id',
        'signwell_recipient_id',
        'signwell_signing_url',
        'signwell_status',
        'signwell_completed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'email_verification_code_hash',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'social_links' => 'array',
        'estimating_software' => 'array',
        'worked_as_construction_va' => 'boolean',
        'referrer_is_employee' => 'boolean',
        'status' => 'integer',
        'can_manage_platform_settings' => 'boolean',
        'email_verification_expires_at' => 'datetime',
        'email_verification_attempts' => 'integer',
        'dashboard_first_visited_at' => 'datetime',
        'user_type' => LearnerUserType::class,
        'candidate_status' => CandidateStatus::class,
        'candidate_status_updated_at' => 'datetime',
        'legal_agreement_accepted_at' => 'datetime',
        'legal_confirmation_email_sent_at' => 'datetime',
        'signwell_completed_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
    ];

    protected function photo(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                // Prefer the live Spatie media file so avatars still work after
                // APP_URL / storage-path changes, and so the navbar matches Profile.
                if ($this->exists) {
                    $media = $this->getMedia('default')
                        ->first(fn ($item) => $item->getCustomProperty('name') === 'profile')
                        ?? $this->getMedia('*', ['name' => 'profile'])->first();

                    if ($media) {
                        return media_public_url($media);
                    }
                }

                return $value ? public_asset_url($value) : null;
            },
            set: fn (?string $value) => ['photo' => $value],
        );
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

        $this->addMediaCollection('id_document')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf']);
    }

    public function sendEmailVerificationNotification(): void
    {
        app(\App\Services\AccountMailService::class)->sendEmailVerification($this);
    }

    public function isAccountActive(): bool
    {
        return (int) $this->status === 1;
    }

    public function canManagePlatformSettings(): bool
    {
        if ($this->role !== 'admin') {
            return false;
        }

        if (MasterAdmin::isProtected($this)) {
            return true;
        }

        return (bool) $this->getAttribute('can_manage_platform_settings');
    }
}
