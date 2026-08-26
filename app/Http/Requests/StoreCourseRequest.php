<?php

namespace App\Http\Requests;

use App\Enums\CourseAudience;
use App\Enums\CoursePricingType;
use App\Enums\ExpiryLimitType;
use App\Enums\TeachingType;
use App\Models\Course\CourseCategory;
use App\Models\Course\CourseCategoryChild;
use App\Models\Instructor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $pricingType = $this->input('pricing_type');
        $isFree = $pricingType === CoursePricingType::FREE->value;

        $instructorId = $this->resolveInstructorId();
        $categoryId = $this->input('course_category_id');
        $categoryChildId = $this->input('course_category_child_id');

        $this->merge([
            'price' => $isFree ? null : ($this->filled('price') ? (float) $this->input('price') : null),
            'discount' => filter_var($this->input('discount'), FILTER_VALIDATE_BOOLEAN),
            'discount_price' => $isFree ? null : ($this->filled('discount_price') ? (float) $this->input('discount_price') : null),
            'instructor_id' => $instructorId,
            'course_category_id' => filled($categoryId) && (int) $categoryId > 0 ? (int) $categoryId : null,
            'course_category_child_id' => filled($categoryChildId) && (int) $categoryChildId > 0 ? (int) $categoryChildId : null,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
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
            'instructor_id' => ['required', Rule::exists(Instructor::class, 'id')],
            'course_category_id' => ['required', Rule::exists(CourseCategory::class, 'id')],
            'course_category_child_id' => ['nullable', Rule::exists(CourseCategoryChild::class, 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'instructor_id.required' => 'Please select a course instructor (or ensure your instructor profile is linked).',
            'instructor_id.exists' => 'The selected instructor is invalid.',
            'course_category_id.required' => 'Please select a course category.',
            'short_description.required' => 'Please enter a short description.',
            'level.required' => 'Please select a course level.',
            'language.required' => 'Please select a course language.',
            'price.required_if' => 'Please enter a price for paid courses.',
        ];
    }

    private function resolveInstructorId(): ?int
    {
        $raw = $this->input('instructor_id');
        if (filled($raw) && (int) $raw > 0) {
            return (int) $raw;
        }

        $user = $this->user();
        if (! $user) {
            return null;
        }

        $system = app('system_settings');
        $isCollaborativeAdmin = $user->role === 'admin'
            && ($system->sub_type ?? null) === TeachingType::COLLABORATIVE->value;

        // Collaborative admins must pick an instructor in the form.
        if ($isCollaborativeAdmin) {
            return null;
        }

        if ($user->instructor_id) {
            return (int) $user->instructor_id;
        }

        $linkedId = Instructor::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->value('id');

        return $linkedId ? (int) $linkedId : null;
    }
}
