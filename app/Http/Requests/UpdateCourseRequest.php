<?php

namespace App\Http\Requests;

use App\Enums\CourseAudience;
use App\Enums\CourseBillingModel;
use App\Enums\CoursePricingType;
use App\Enums\ExpiryLimitType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        $pricingType = request('pricing_type');
        $isFree = $pricingType && $pricingType === CoursePricingType::FREE->value;
        $billingModel = request('billing_model', CourseBillingModel::ONE_TIME->value);
        $isSubscription = !$isFree && $billingModel === CourseBillingModel::SUBSCRIPTION->value;
        
        // Convert numeric fields
        $this->merge([
            'price' => $isFree ? null : (request('price') ? (float) request('price') : null),
            'discount' => $isFree || $isSubscription ? false : filter_var(request('discount'), FILTER_VALIDATE_BOOLEAN),
            'discount_price' => ($isFree || $isSubscription) ? null : (request('discount_price') ? (float) request('discount_price') : null),
            'billing_model' => $isFree ? CourseBillingModel::ONE_TIME->value : $billingModel,
            'subscription_price' => $isSubscription && request('subscription_price')
                ? (float) request('subscription_price')
                : null,
            'instructor_id' => request('instructor_id') ? (int) request('instructor_id') : null,
            'course_category_id' => (int) request('course_category_id', 0),
            'course_category_child_id' => request('course_category_child_id') ? (int) request('course_category_child_id') : null,
            'final_exam_id' => request('final_exam_id') ? (int) request('final_exam_id') : null,
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Common rules for all tabs
        $rules = [
            'tab' => 'required|string',
        ];

        // Merge with tab-specific rules
        return array_merge($rules, $this->getTabSpecificRules());
    }

    /**
     * Get validation rules specific to the current tab
     */
    private function getTabSpecificRules(): array
    {
        $tab = request('tab');
        return match ($tab) {
            'basic' => $this->basicTabRules(),
            'pricing' => $this->pricingTabRules(),
            'media' => $this->mediaTabRules(),
            'seo' => $this->seoTabRules(),
            default => [],
        };
    }

    /**
     * Validation rules for the basic tab
     */
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

    /**
     * Validation rules for the pricing tab
     */
    private function pricingTabRules(): array
    {
        $free = CoursePricingType::FREE->value;
        $paid = CoursePricingType::PAID->value;
        $lifetime = ExpiryLimitType::LIFETIME->value;
        $limited = ExpiryLimitType::LIMITED_TIME->value;
        $oneTime = CourseBillingModel::ONE_TIME->value;
        $subscription = CourseBillingModel::SUBSCRIPTION->value;
        $billingModel = request('billing_model', $oneTime);

        return [
            'pricing_type' => "required|string|in:$free,$paid",
            'billing_model' => "nullable|string|in:$oneTime,$subscription|required_if:pricing_type,$paid",
            'price' => "nullable|numeric|min:1|required_if:billing_model,$oneTime",
            'subscription_price' => "nullable|numeric|min:1|required_if:billing_model,$subscription",
            'discount' => 'boolean',
            'discount_price' => $billingModel === $oneTime
                ? 'nullable|numeric|min:1|lt:price|required_if:discount,true'
                : 'nullable',
            'expiry_type' => "required|string|in:$lifetime,$limited",
            'expiry_duration' => "nullable|string|required_if:expiry_type,$limited",
        ];
    }

    /**
     * Validation rules for the media tab
     */
    private function mediaTabRules(): array
    {
        return [
            'thumbnail' => 'nullable|image|max:2048',
            'banner' => 'nullable|image|max:2048',
            'preview_type' => 'nullable|string|in:video_url,video',
            'preview' => 'nullable|string',
        ];
    }

    /**
     * Validation rules for the SEO tab
     */
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
