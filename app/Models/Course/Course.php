<?php

namespace App\Models\Course;

use App\Enums\CourseAudience;
use App\Enums\CourseBillingModel;
use App\Enums\CourseStatusType;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Modules\Exam\Models\Exam;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Course extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'title',
        'slug',
        'course_type',
        'status',
        'launch_at',
        'allow_staff_preview',
        'allow_internal_preview',
        'level',
        'short_description',
        'description',
        'language',

        'pricing_type',
        'billing_model',
        'audience',
        'price',
        'discount',
        'discount_price',
        'subscription_price',
        'stripe_product_id',
        'stripe_price_id',
        'drip_content',

        'thumbnail',
        'banner',
        'preview',

        'expiry_type',
        'expiry_duration',
        'training_hours',
        'created_from',

        'meta_title',
        'meta_keywords',
        'meta_description',
        'og_title',
        'og_description',

        'instructor_id',
        'course_category_id',
        'course_category_child_id',
        'final_exam_id',
    ];

    protected $casts = [
        'audience' => CourseAudience::class,
        'billing_model' => CourseBillingModel::class,
        'subscription_price' => 'decimal:2',
        'launch_at' => 'datetime',
        'allow_staff_preview' => 'boolean',
        'allow_internal_preview' => 'boolean',
    ];

    protected $appends = [
        'is_coming_soon',
        'is_enrollment_open',
        'can_preview_before_launch',
    ];

    protected function thumbnail(): Attribute
    {
        return Attribute::make(get: fn (?string $value) => public_asset_url($value));
    }

    protected function banner(): Attribute
    {
        return Attribute::make(get: fn (?string $value) => public_asset_url($value));
    }

    protected function preview(): Attribute
    {
        return Attribute::make(get: fn (?string $value) => public_asset_url($value));
    }

    public function scopeVisibleInCatalog(Builder $query, ?User $user = null): Builder
    {
        if (self::catalogViewerCanSeeInternal($user)) {
            return $query;
        }

        return $query->whereIn('audience', [
            CourseAudience::PUBLIC->value,
            CourseAudience::BOTH->value,
        ]);
    }

    public function scopeListedInCatalog(Builder $query): Builder
    {
        return $query->whereIn('status', [
            CourseStatusType::APPROVED->value,
            CourseStatusType::UPCOMING->value,
        ]);
    }

    public function scopeLaunched(Builder $query): Builder
    {
        return $query->where(function (Builder $builder) {
            $builder->whereNull('launch_at')
                ->orWhere('launch_at', '<=', now());
        });
    }

    public function scopeEnrollmentOpen(Builder $query): Builder
    {
        return $query
            ->where('status', CourseStatusType::APPROVED->value)
            ->launched();
    }

    public function isComingSoon(): bool
    {
        if ($this->status === CourseStatusType::UPCOMING->value) {
            return true;
        }

        return $this->launch_at !== null && $this->launch_at->isFuture();
    }

    public function isEnrollmentOpen(): bool
    {
        if ($this->status !== CourseStatusType::APPROVED->value) {
            return false;
        }

        if ($this->launch_at && $this->launch_at->isFuture()) {
            return false;
        }

        return true;
    }

    public function canPreviewBeforeLaunch(?User $user = null): bool
    {
        $user ??= Auth::user();

        if (!$user || !$this->isComingSoon()) {
            return false;
        }

        if ($this->allow_internal_preview && $user->isEmployeeLearner()) {
            return true;
        }

        if (!($this->allow_staff_preview ?? true)) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'instructor'
            && (int) $user->instructor_id === (int) $this->instructor_id;
    }

    public function getCanPreviewBeforeLaunchAttribute(): bool
    {
        return $this->canPreviewBeforeLaunch();
    }

    public function isCatalogListed(): bool
    {
        return in_array($this->status, [
            CourseStatusType::APPROVED->value,
            CourseStatusType::UPCOMING->value,
        ], true);
    }

    public function isPubliclyViewable(?User $user = null): bool
    {
        if (!$this->isVisibleToUser($user)) {
            return false;
        }

        if ($this->isCatalogListed()) {
            return true;
        }

        return $user !== null && in_array($user->role, ['admin', 'instructor'], true);
    }

    public function getIsComingSoonAttribute(): bool
    {
        return $this->isComingSoon();
    }

    public function getIsEnrollmentOpenAttribute(): bool
    {
        return $this->isEnrollmentOpen();
    }

    public static function catalogViewerCanSeeInternal(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->isEmployeeLearner()) {
            return true;
        }

        return in_array($user->role, ['admin', 'instructor'], true);
    }

    public function isVisibleToUser(?User $user): bool
    {
        if ($this->audience !== CourseAudience::INTERNAL) {
            return true;
        }

        return self::catalogViewerCanSeeInternal($user);
    }

    public function course_category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class);
    }

    public function course_category_child(): BelongsTo
    {
        return $this->belongsTo(CourseCategoryChild::class);
    }

    public function live_classes(): HasMany
    {
        return $this->hasMany(CourseLiveClass::class)->orderBy('class_date_and_time', 'asc');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CourseAssignment::class)->orderBy('created_at', 'desc');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(CourseSection::class)->orderBy('sort', 'asc');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(SectionLesson::class)->orderBy('sort', 'asc');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class)->orderBy('created_at', 'desc');
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(CourseFaq::class)->orderBy('sort', 'asc');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(CourseRequirement::class)->orderBy('sort', 'asc');
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(CourseOutcome::class)->orderBy('sort', 'asc');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function final_exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'final_exam_id');
    }

    public function forums(): HasMany
    {
        return $this->hasMany(CourseForum::class)->orderBy('created_at', 'desc');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CourseReview::class)->orderBy('created_at', 'desc');
    }

    public function launchNotifications(): HasMany
    {
        return $this->hasMany(CourseLaunchNotification::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(CourseCoupon::class)->orderBy('created_at', 'desc');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(\App\Models\Subscription::class);
    }

    public function usesSubscriptionBilling(): bool
    {
        return $this->billing_model === CourseBillingModel::SUBSCRIPTION;
    }

    public function subscriptionCheckoutPrice(): ?float
    {
        if (!$this->usesSubscriptionBilling()) {
            return null;
        }

        return $this->subscription_price !== null
            ? (float) $this->subscription_price
            : ($this->price !== null ? (float) $this->price : null);
    }
}
