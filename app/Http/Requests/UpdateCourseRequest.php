<?php

namespace App\Http\Requests;

use App\Enums\CourseAudience;
use App\Enums\CourseBillingModel;
use App\Enums\CoursePricingType;
use App\Enums\ExpiryLimitType;
use App\Http\Requests\Concerns\NormalizesLaunchAt;
use Illuminate\Foundation\Http\FormRequest;

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
            'seo' => null,
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
    }

    private function preparePricingTab(): void
    {
        $pricingType = (string) $this->input('pricing_type', '');
        $isFree = $pricingType === CoursePricingType::FREE->value;
        $billingModel = (string) $this->input('billing_model', CourseBillingModel::ONE_TIME->value);
        $isSubscription = ! $isFree && $billingModel === CourseBillingModel::SUBSCRIPTION->value;
        $isOneTime = ! $isFree && ! $isSubscription;
        $launchOfferEnabled = ! $isFree && filter_var($this->input('launch_offer_enabled'), FILTER_VALIDATE_BOOLEAN);

        // Launch offers always use monthly subscription after deposit/full pay.
        if ($launchOfferEnabled) {
            $billingModel = CourseBillingModel::SUBSCRIPTION->value;
            $isSubscription = true;
            $isOneTime = false;
        }

        $this->merge([
            'pricing_type' => $pricingType,
            'billing_model' => $isFree ? CourseBillingModel::ONE_TIME->value : $billingModel,
            'price' => $isOneTime && $this->filled('price') ? (float) $this->input('price') : null,
            'subscription_price' => $isSubscription && $this->filled('subscription_price')
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
                ? $this->input('launch_offer_starts_at')
                : null,
            'launch_offer_ends_at' => $launchOfferEnabled && $this->filled('launch_offer_ends_at')
                ? $this->input('launch_offer_ends_at')
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
                ? $this->input('launch_subscription_trial_ends_at')
                : null,
            'launch_full_upfront_price' => $launchOfferEnabled && $this->filled('launch_full_upfront_price')
                ? (float) $this->input('launch_full_upfront_price')
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

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'tab' => 'required|string|in:basic,pricing,media,seo,info,status',
        ], $this->getTabSpecificRules());
    }

    public function messages(): array
    {
        return [
            'launch_at.after' => 'Launch date and time must be in the future.',
            'launch_at.required_if' => 'Launch date is required for Coming Soon courses.',
            'price.required' => 'Please enter a course price.',
            'subscription_price.required' => 'Please enter a monthly subscription price.',
        ];
    }

    private function getTabSpecificRules(): array
    {
        return match ((string) $this->input('tab')) {
            'basic' => $this->basicTabRules(),
            'pricing' => $this->pricingTabRules(),
            'media' => $this->mediaTabRules(),
            'seo' => $this->seoTabRules(),
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
        $pricingType = (string) $this->input('pricing_type');
        $billingModel = (string) $this->input('billing_model', $oneTime);
        $isPaid = $pricingType === $paid;
        $isOneTime = $isPaid && $billingModel === $oneTime;
        $isSubscription = $isPaid && $billingModel === $subscription;

        return [
            'pricing_type' => "required|string|in:$free,$paid",
            'billing_model' => $isPaid
                ? "required|string|in:$oneTime,$subscription"
                : "nullable|string|in:$oneTime,$subscription",
            'price' => $isOneTime
                ? 'required|numeric|min:1'
                : 'nullable|numeric|min:1',
            'subscription_price' => ($isSubscription || filter_var($this->input('launch_offer_enabled'), FILTER_VALIDATE_BOOLEAN))
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
            'launch_subscription_trial_ends_at' => 'nullable|date|required_if:launch_offer_enabled,true',
            'launch_full_upfront_price' => 'nullable|numeric|min:1|required_if:launch_offer_enabled,true',
        ];
    }

    private function mediaTabRules(): array
    {
        return [
            'thumbnail' => 'nullable|image|max:2048',
            'banner' => 'nullable|image|max:2048',
            'preview_type' => 'nullable|string|in:video_url,video',
            'preview' => 'nullable|string',
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
}
