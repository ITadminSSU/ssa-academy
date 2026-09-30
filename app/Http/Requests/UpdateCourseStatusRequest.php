<?php

namespace App\Http\Requests;

use App\Enums\CourseStatusType;
use App\Http\Requests\Concerns\NormalizesLaunchAt;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseStatusRequest extends FormRequest
{
    use NormalizesLaunchAt;

    protected function prepareForValidation(): void
    {
        $this->normalizeLaunchAtInput();

        if ($this->input('status') === CourseStatusType::COMING_SOON->value) {
            $this->merge(['launch_at' => null]);
        }
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
        return [
            'status' => 'required|string|in:draft,upcoming,coming_soon,pending,approved,rejected',
            'launch_at' => 'nullable|date|required_if:status,upcoming|after:now',
            'feedback' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'launch_at.after' => 'Launch date and time must be in the future.',
            'launch_at.required_if' => 'Launch date is required for Coming Soon courses.',
        ];
    }
}
