<?php

namespace App\Http\Requests;

use App\Enums\CourseAudience;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\User;
use App\Support\AuthenticatedUser;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourseEnrollmentRequest extends FormRequest
{
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
        return [
            'user_id' => 'required|exists:users,id',
            'course_id' => [
                'required',
                'exists:courses,id',
                function ($attribute, $value, $fail) {
                    if (CourseEnrollment::where('user_id', $this->user_id)
                        ->where('course_id', $value)
                        ->exists()
                    ) {
                        $fail('This user is already enrolled in this course.');
                    }
                },
            ],
            'enrollment_type' => 'required|string|in:free,paid',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => AuthenticatedUser::resolve(
                $this->input('user_id') ? (int) $this->input('user_id') : null,
                allowAdminDelegation: isAdmin(),
            ),
        ]);

        $user = User::find($this->input('user_id'));
        $course = Course::find($this->input('course_id'));

        if ($user?->qualifiesForFreeCourseAccess()) {
            $this->merge(['enrollment_type' => 'free']);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = User::find($this->input('user_id'));
            $course = Course::find($this->input('course_id'));

            if (!$user || !$course) {
                return;
            }

            if (
                !isAdmin()
                && $course->audience === CourseAudience::INTERNAL
                && !$user->isEmployeeLearner()
            ) {
                $validator->errors()->add('course_id', 'This course is only available to internal employees.');
            }

            if (
                !$user->qualifiesForFreeCourseAccess()
                && $course->pricing_type === 'paid'
                && $this->input('enrollment_type') === 'free'
            ) {
                $validator->errors()->add('enrollment_type', 'Paid courses require payment for external learners.');
            }

            if (!isAdmin() && !$course->isEnrollmentOpen()) {
                $validator->errors()->add('course_id', 'This course is not available for enrollment yet.');
            }
        });
    }
}
