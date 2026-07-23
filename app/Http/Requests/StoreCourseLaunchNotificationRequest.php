<?php

namespace App\Http\Requests;

use App\Models\Course\Course;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourseLaunchNotificationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->user() && !$this->filled('email')) {
            $this->merge([
                'email' => $this->user()->email,
            ]);
        }

        if ($this->filled('email')) {
            $this->merge([
                'email' => strtolower(trim((string) $this->input('email'))),
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Course $course */
        $course = $this->route('course');

        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Course|null $course */
            $course = $this->route('course');

            if (!$course || !$course->isComingSoon()) {
                $validator->errors()->add('email', 'Notifications are only available for upcoming courses.');
            }
        });
    }
}
