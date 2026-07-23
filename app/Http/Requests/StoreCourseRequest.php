<?php

namespace App\Http\Requests;

use App\Enums\CourseAudience;
use App\Enums\CoursePricingType;
use App\Enums\ExpiryLimitType;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        $pricingType = request('pricing_type');
        $isFree = $pricingType === CoursePricingType::FREE->value;
        
        // Convert numeric fields
        $this->merge([
            'price' => $isFree ? null : (request('price') ? (float) request('price') : null),
            'discount' => filter_var(request('discount'), FILTER_VALIDATE_BOOLEAN),
            'discount_price' => $isFree ? null : (request('discount_price') ? (float) request('discount_price') : null),
            'instructor_id' => (int) request('instructor_id'),
            'course_category_id' => (int) request('course_category_id'),
            'course_category_child_id' => request('course_category_child_id') ? (int) request('course_category_child_id') : null,
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
        $free = CoursePricingType::FREE->value;
        $paid = CoursePricingType::PAID->value;
        $internal = CourseAudience::INTERNAL->value;
        $public = CourseAudience::PUBLIC->value;
        $both = CourseAudience::BOTH->value;
        $lifetime = ExpiryLimitType::LIFETIME->value;
        $limited = ExpiryLimitType::LIMITED_TIME->value;

        return [
            'title' => 'required|string|max:255',
            'short_description' => 'required|string',
            'description' => 'nullable|string',
            'status' => 'required|string',
            'launch_at' => 'nullable|date|required_if:status,upcoming',
            'level' => 'required|string',
            'language' => 'required|string|max:255',
            'pricing_type' => "required|string|in:$free,$paid",
            'audience' => "required|string|in:$internal,$public,$both",
            'price' => "nullable|numeric|min:1|required_if:pricing_type,$paid",
            'discount' => 'boolean',
            'discount_price' => 'nullable|numeric|min:1|lt:price|required_if:discount,true',
            'expiry_type' => "required|string|in:$lifetime,$limited",
            'expiry_duration' => "nullable|string|required_if:expiry_type,$limited",
            'training_hours' => 'nullable|string|max:50',
            'thumbnail' => 'nullable|image|max:2048',
            'created_from' => 'nullable|string|in:web,api',
            'instructor_id' => 'required|exists:instructors,id',
            'course_category_id' => 'required|exists:course_categories,id',
            'course_category_child_id' => 'nullable|exists:course_category_children,id',
        ];
    }
}
