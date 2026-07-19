<?php

namespace App\Models\Course;

use App\Enums\CourseAudience;
use App\Enums\CourseBillingModel;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
