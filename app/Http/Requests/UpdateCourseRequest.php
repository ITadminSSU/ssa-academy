<?php

namespace App\Http\Requests;

use App\Enums\CourseAudience;
use App\Enums\CourseBillingModel;
use App\Enums\CoursePricingType;
use App\Enums\CourseStatusType;
use App\Enums\ExpiryLimitType;
use App\Http\Requests\Concerns\NormalizesLaunchAt;
use App\Models\Course\Course;
use App\Support\CourseHero;
use App\Support\DrywallLanding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
{
    use NormalizesLaunchAt;

    protected function prepareForValidation(): void
    {
        $tab = (string) $this->input('tab', '');

        match ($tab) {
            'basic' => $this->prepareBasicTab(),
            'pricing' => $this->preparePricingTab(),
            'media' => $this->prepareMediaTab(),
            'hero' => $this->prepareHeroTab(),
            'seo' => null,
            'landing' => $this->prepareLandingTab(),
            default => null,
        };
    }

    private function prepareBasicTab(): void
    {
        $this->merge([
            'instructor_id' => $this->filled('instructor_id') ? (int) $this->input('instructor_id') : null,
            'course_category_id' => (int) $this->input('course_category_id', 0),
            'course_category_child_id' => $this->filled('course_category_child_id')
                ? (int) $this->input('course_category_child_id')
                : null,
            'final_exam_id' => $this->filled('final_exam_id') ? (int) $this->input('final_exam_id') : null,
            'allow_staff_preview' => filter_var($this->input('allow_staff_preview', true), FILTER_VALIDATE_BOOLEAN),
            'allow_internal_preview' => filter_var($this->input('allow_internal_preview', false), FILTER_VALIDATE_BOOLEAN),
        ]);

        $this->normalizeLaunchAtInput();

        if ($this->input('status') === CourseStatusType::COMING_SOON->value) {
            $this->merge(['launch_at' => null]);
        }
    }

    private function preparePricingTab(): void
    {
        $pricingType = (string) $this->input('pricing_type', '');
        $isFree = $pricingType === CoursePricingType::FREE->value;
        $billingModel = (string) $this->input('billing_model', CourseBillingModel::ONE_TIME->value);
        $isUpfrontSubscription = ! $isFree && $billingModel === CourseBillingModel::UPFRONT_SUBSCRIPTION->value;
        $isPreRegistrationSubscription = ! $isFree && $billingModel === CourseBillingModel::PRE_REGISTER_SUBSCRIPTION->value;
        $launchOfferEnabled = $isPreRegistrationSubscription;

        $isMonthlyOnly = ! $isFree && $billingModel === CourseBillingModel::SUBSCRIPTION->value;
        $isOneTime = ! $isFree && $billingModel === CourseBillingModel::ONE_TIME->value;
        $needsMonthlyPrice = $isMonthlyOnly || $isUpfrontSubscription || $launchOfferEnabled;
        $needsUpfrontPrice = $isOneTime || $isUpfrontSubscription;
        $catalogPromoAllowed = ! $isFree && ($isOneTime || $isUpfrontSubscription || $launchOfferEnabled);
        $catalogPromoOn = $catalogPromoAllowed
            && filter_var($this->input('catalog_coupon_promo'), FILTER_VALIDATE_BOOLEAN);

        $this->merge([
            'pricing_type' => $pricingType,
            'billing_model' => $isFree ? CourseBillingModel::ONE_TIME->value : $billingModel,
            'price' => $needsUpfrontPrice && $this->filled('price') ? (float) $this->input('price') : null,
            'subscription_price' => $needsMonthlyPrice && $this->filled('subscription_price')
                ? (float) $this->input('subscription_price')
                : null,
            'discount' => $isOneTime
                ? filter_var($this->input('discount'), FILTER_VALIDATE_BOOLEAN)
                : false,
            'discount_price' => $isOneTime
                && filter_var($this->input('discount'), FILTER_VALIDATE_BOOLEAN)
                && $this->filled('discount_price')
                    ? (float) $this->input('discount_price')
                    : null,
            'expiry_duration' => (string) $this->input('expiry_type') === ExpiryLimitType::LIMITED_TIME->value
                ? ($this->input('expiry_duration') ?: null)
                : null,
            'launch_offer_enabled' => $launchOfferEnabled,
            'launch_offer_starts_at' => $launchOfferEnabled && $this->filled('launch_offer_starts_at')
                ? $this->normalizeAppTimezoneDateTime($this->input('launch_offer_starts_at'))
                : null,
            'launch_offer_ends_at' => $launchOfferEnabled && $this->filled('launch_offer_ends_at')
                ? $this->normalizeAppTimezoneDateTime($this->input('launch_offer_ends_at'))
                : null,
            'launch_list_price' => $launchOfferEnabled && $this->filled('launch_list_price')
                ? (float) $this->input('launch_list_price')
                : null,
            'launch_offer_price' => $launchOfferEnabled && $this->filled('launch_offer_price')
                ? (float) $this->input('launch_offer_price')
                : null,
            'launch_deposit_amount' => $launchOfferEnabled && $this->filled('launch_deposit_amount')
                ? (float) $this->input('launch_deposit_amount')
                : null,
            'launch_balance_amount' => $launchOfferEnabled && $this->filled('launch_balance_amount')
                ? (float) $this->input('launch_balance_amount')
                : null,
            'launch_balance_grace_days' => $launchOfferEnabled
                ? (int) ($this->input('launch_balance_grace_days') ?: 5)
                : 5,
            'launch_subscription_trial_ends_at' => $launchOfferEnabled && $this->filled('launch_subscription_trial_ends_at')
                ? $this->normalizeAppTimezoneDateTime($this->input('launch_subscription_trial_ends_at'))
                : null,
            'launch_full_upfront_price' => $launchOfferEnabled && $this->filled('launch_full_upfront_price')
                ? (float) $this->input('launch_full_upfront_price')
                : null,
            'catalog_coupon_promo' => $catalogPromoOn,
            'catalog_coupon_off_remaining' => $catalogPromoOn && $this->filled('catalog_coupon_off_remaining')
                ? (float) $this->input('catalog_coupon_off_remaining')
                : null,
        ]);
    }

    private function prepareMediaTab(): void
    {
        // Keep preview string/null as submitted; files are handled by the service.
        if (! $this->has('preview')) {
            $this->merge(['preview' => null]);
        }
    }

    private function prepareHeroTab(): void
    {
        $this->merge([
            'hero_eyebrow' => trim((string) $this->input('hero_eyebrow', '')),
            'hero_headline' => trim((string) $this->input('hero_headline', '')),
            'hero_paragraph' => trim((string) $this->input('hero_paragraph', '')),
            'hero_highlights' => CourseHero::phrasesFromInput($this->input('hero_highlights', '')),
            'hero_video_source' => $this->input('hero_video_source') === 'upload' ? 'upload' : 'link',
            'hero_video_url' => trim((string) $this->input('hero_video_url', '')),
            'hero_video_stored' => trim((string) $this->input('hero_video_stored', '')),
            'hero_photo' => $this->hasFile('hero_photo') ? $this->file('hero_photo') : null,
        ]);
    }

    private function prepareLandingTab(): void
    {
        $course = Course::query()->find($this->route('id'));

        if (! DrywallLanding::isDrywallCourse($course)) {
            abort(404);
        }

        $files = $this->file('reviews', []);
        if (! is_array($files)) {
            $files = [];
        }

        $submitted = $this->input('reviews', []);
        if (! is_array($submitted)) {
            $submitted = [];
        }

        $reviews = [];

        foreach ($submitted as $index => $review) {
            if (! is_array($review)) {
                continue;
            }

            $type = ($review['type'] ?? '') === 'image' ? 'image' : 'written';
            $quote = trim((string) ($review['quote'] ?? ''));
            $name = trim((string) ($review['name'] ?? ''));
            $upload = $files[$index]['image'] ?? null;

            if ($type === 'written' && $quote === '' && $name === '') {
                continue;
            }

            $reviews[] = [
                'type' => $type,
                'quote' => $type === 'written' ? $quote : '',
                'name' => $name,
                'existing_image' => $type === 'image'
                    ? (DrywallLanding::storedReviewImage((string) ($review['existing_image'] ?? '')) ?? '')
                    : '',
                'image' => $type === 'image' && $upload instanceof UploadedFile ? $upload : null,
            ];
        }

        $this->files->remove('reviews');

        $this->merge([
            'reviews' => $reviews,
            'countdown_ends_at' => $this->normalizeAppTimezoneDateTime($this->input('countdown_ends_at')),
            'video_source' => $this->input('video_source') === 'upload' ? 'upload' : 'link',
            'video_url' => $this->landingVideoUrl(),
            'video_stored_url' => DrywallLanding::storedReviewImage((string) $this->input('video_stored_url', '')) ?? '',
            'video_duration' => trim((string) $this->input('video_duration', '')),
            'coach_line' => trim((string) $this->input('coach_line', '')),
            'hero_photo' => $this->hasFile('hero_photo') ? $this->file('hero_photo') : null,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'tab' => 'required|string|in:basic,pricing,media,hero,seo,info,status,landing',
        ], $this->getTabSpecificRules());
    }

    public function messages(): array
    {
        return [
            'video_url.required' => 'Upload a video for the landing page.',
            'hero_photo.image' => 'The hero photo must be a JPG, PNG, or WebP image.',
            'hero_photo.mimes' => 'The hero photo must be a JPG, PNG, or WebP image.',
            'hero_photo.max' => 'The hero photo must be 100 MB or smaller.',
            'reviews.*.image.required' => 'Upload a picture for this review.',
            'reviews.*.image.image' => 'The review picture must be an image file.',
            'launch_at.after' => 'Launch date and time must be in the future.',
            'launch_at.required_if' => 'Launch date is required for Coming Soon courses.',
            'price.required' => 'Please enter a course price.',
            'subscription_price.required' => 'Please enter a monthly subscription price.',
            'catalog_coupon_off_remaining.lt' => 'The coupon amount must be less than the course price.',
            'catalog_coupon_off_remaining.lte' => 'The coupon amount cannot be more than the remaining balance.',
        ];
    }

    private function getTabSpecificRules(): array
    {
        return match ((string) $this->input('tab')) {
            'basic' => $this->basicTabRules(),
            'pricing' => $this->pricingTabRules(),
            'media' => $this->mediaTabRules(),
            'hero' => $this->heroTabRules(),
            'seo' => $this->seoTabRules(),
            'landing' => $this->landingTabRules(),
            default => [],
        };
    }

    private function basicTabRules(): array
    {
        $internal = CourseAudience::INTERNAL->value;
        $public = CourseAudience::PUBLIC->value;
        $both = CourseAudience::BOTH->value;

        return [
            'title' => 'required|string|max:255',
            'short_description' => 'required|string',
            'description' => 'nullable|string',
            'status' => 'required|string',
            'launch_at' => 'nullable|date|required_if:status,upcoming|after:now',
            'allow_staff_preview' => 'sometimes|boolean',
            'allow_internal_preview' => 'sometimes|boolean',
            'level' => 'required|string',
            'language' => 'required|string|max:255',
            'instructor_id' => 'nullable|exists:instructors,id',
            'course_category_id' => 'required|exists:course_categories,id',
            'course_category_child_id' => 'nullable|exists:course_category_children,id',
            'audience' => "required|string|in:$internal,$public,$both",
            'final_exam_id' => 'nullable|integer|exists:exams,id',
            'training_hours' => 'nullable|string|max:50',
        ];
    }

    private function pricingTabRules(): array
    {
        $free = CoursePricingType::FREE->value;
        $paid = CoursePricingType::PAID->value;
        $lifetime = ExpiryLimitType::LIFETIME->value;
        $limited = ExpiryLimitType::LIMITED_TIME->value;
        $oneTime = CourseBillingModel::ONE_TIME->value;
        $subscription = CourseBillingModel::SUBSCRIPTION->value;
        $upfrontSubscription = CourseBillingModel::UPFRONT_SUBSCRIPTION->value;
        $preRegisterSubscription = CourseBillingModel::PRE_REGISTER_SUBSCRIPTION->value;
        $billingModels = "$oneTime,$subscription,$upfrontSubscription,$preRegisterSubscription";
        $pricingType = (string) $this->input('pricing_type');
        $billingModel = (string) $this->input('billing_model', $oneTime);
        $isPaid = $pricingType === $paid;
        $isOneTime = $isPaid && $billingModel === $oneTime;
        $isUpfrontSubscription = $isPaid && $billingModel === $upfrontSubscription;
        $isPreRegistrationSubscription = $isPaid && $billingModel === $preRegisterSubscription;
        $isSubscription = $isPaid && ($billingModel === $subscription || $isUpfrontSubscription || $isPreRegistrationSubscription);
        $launchOfferEnabled = $isPreRegistrationSubscription;

        return [
            'pricing_type' => "required|string|in:$free,$paid",
            'billing_model' => $isPaid
                ? "required|string|in:$billingModels"
                : "nullable|string|in:$billingModels",
            'price' => ($isOneTime || $isUpfrontSubscription)
                ? 'required|numeric|min:1'
                : 'nullable|numeric|min:1',
            'subscription_price' => ($isSubscription || $launchOfferEnabled)
                ? 'required|numeric|min:1'
                : 'nullable|numeric|min:1',
            'discount' => 'boolean',
            'discount_price' => $isOneTime
                ? 'nullable|numeric|min:1|lt:price|required_if:discount,true'
                : 'nullable',
            'expiry_type' => "required|string|in:$lifetime,$limited",
            'expiry_duration' => "nullable|string|required_if:expiry_type,$limited",
            'launch_offer_enabled' => 'boolean',
            'launch_offer_starts_at' => 'nullable|date|required_if:launch_offer_enabled,true',
            'launch_offer_ends_at' => 'nullable|date|after_or_equal:launch_offer_starts_at|required_if:launch_offer_enabled,true',
            'launch_list_price' => 'nullable|numeric|min:1|required_if:launch_offer_enabled,true',
            'launch_offer_price' => 'nullable|numeric|min:1|required_if:launch_offer_enabled,true',
            'launch_deposit_amount' => 'nullable|numeric|min:1|required_if:launch_offer_enabled,true',
            'launch_balance_amount' => 'nullable|numeric|min:1|required_if:launch_offer_enabled,true',
            'launch_balance_grace_days' => 'nullable|integer|min:1|max:30',
            'launch_subscription_trial_ends_at' => 'nullable|date',
            'launch_full_upfront_price' => 'nullable|numeric|min:1|required_if:launch_offer_enabled,true',
            'catalog_coupon_promo' => 'boolean',
            'catalog_coupon_off_remaining' => [
                'nullable',
                'numeric',
                'min:0.01',
                Rule::requiredIf(fn () => (bool) $this->boolean('catalog_coupon_promo')),
                Rule::when($launchOfferEnabled, ['lte:launch_balance_amount']),
                Rule::when($isOneTime || $isUpfrontSubscription, ['lt:price']),
            ],
        ];
    }

    private function mediaTabRules(): array
    {
        return [
            'thumbnail' => 'nullable|image|max:102400',
            'banner' => 'nullable|image|max:102400',
            'preview_type' => 'nullable|string|in:video_url,video',
            'preview' => 'nullable|string',
        ];
    }

    private function heroTabRules(): array
    {
        return [
            'hero_eyebrow' => 'nullable|string|max:80',
            'hero_headline' => 'nullable|string|max:200',
            'hero_paragraph' => 'nullable|string|max:2000',
            'hero_highlights' => 'array|max:12',
            'hero_highlights.*' => 'string|max:120',
            'hero_video_source' => 'required|in:link,upload',
            'hero_video_url' => 'nullable|string|max:2048',
            'hero_video_stored' => 'nullable|string|max:8192',
            'hero_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:102400',
        ];
    }

    private function seoTabRules(): array
    {
        return [
            'meta_title' => 'nullable|string|max:255',
            'meta_keywords' => 'nullable|string',
            'meta_description' => 'nullable|string',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string',
        ];
    }

    private function landingVideoUrl(): ?string
    {
        if ($this->input('video_source') === 'upload') {
            return DrywallLanding::storedReviewImage((string) $this->input('video_stored_url', '')) ?: null;
        }

        return $this->filled('video_url') ? trim((string) $this->input('video_url')) : null;
    }

    private function landingTabRules(): array
    {
        $rules = [
            'countdown_ends_at' => 'required|date',
            'hero_photo' => 'nullable|image|max:10240',
            'video_source' => 'required|in:link,upload',
            'video_url' => $this->input('video_source') === 'upload' ? 'required|string|max:2048' : 'nullable|url|max:2048',
            'video_stored_url' => 'nullable|string|max:2048',
            'video_duration' => 'required|string|max:20',
            'coach_line' => 'required|string|max:160',
            'embed_code' => [
                'required',
                'string',
                'max:20000',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (DrywallLanding::sanitizeEmbed((string) $value) === null) {
                        $fail('Paste the GoHighLevel iframe from api.leadconnectorhq.com.');
                    }
                },
            ],
            'reviews' => 'array',
        ];

        $reviews = $this->input('reviews', []);

        if (! is_array($reviews)) {
            return $rules;
        }

        foreach ($reviews as $index => $review) {
            if (! is_array($review)) {
                $rules["reviews.$index"] = 'array';

                continue;
            }

            $isImage = ($review['type'] ?? '') === 'image';

            $rules["reviews.$index.type"] = 'required|in:written,image';
            $rules["reviews.$index.quote"] = $isImage ? 'nullable|string|max:500' : 'required|string|max:500';
            $rules["reviews.$index.name"] = $isImage ? 'nullable|string|max:120' : 'required|string|max:120';
            $rules["reviews.$index.existing_image"] = 'nullable|string|max:2048';
            $rules["reviews.$index.image"] = $isImage && ($review['existing_image'] ?? '') === ''
                ? 'required|image|max:10240'
                : 'nullable|image|max:10240';
        }

        return $rules;
    }
}
