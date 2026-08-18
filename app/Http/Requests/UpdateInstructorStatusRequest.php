<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateInstructorStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => 'required|string|in:approved,rejected,pending',
            'feedback' => 'nullable|string',
        ];
    }

    protected function prepareForValidation(): void
    {
        $feedback = $this->input('feedback');

        if (! is_string($feedback)) {
            return;
        }

        $plainText = trim(preg_replace('/\s+/', ' ', strip_tags($feedback)) ?? '');

        if ($plainText === '') {
            $this->merge(['feedback' => null]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('status') !== 'rejected') {
                return;
            }

            $feedback = $this->input('feedback');

            if (! is_string($feedback) || trim(strip_tags($feedback)) === '') {
                $validator->errors()->add('feedback', 'Feedback is required when rejecting an application.');
            }
        });
    }
}
