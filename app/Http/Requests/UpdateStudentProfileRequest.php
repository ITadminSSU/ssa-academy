<?php

namespace App\Http\Requests;

use App\Support\PersonName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateStudentProfileRequest extends FormRequest
{
    /** 5 GB, in kilobytes. Laravel's file max rule uses kilobytes. */
    public const PROFILE_DOCUMENT_MAX_KILOBYTES = 5 * 1024 * 1024;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('remove_photo')) {
            $this->merge([
                'remove_photo' => filter_var($this->input('remove_photo'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->has('facebook')) {
            $facebook = trim((string) $this->input('facebook'));
            $this->merge([
                'facebook' => $facebook === '' ? null : $facebook,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'given_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:15360',
            'remove_photo' => 'nullable|boolean',
            'facebook' => ['nullable', 'string', 'max:255', 'url'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'given_name.required' => 'Please enter your given name.',
            'facebook.url' => 'Enter a valid Facebook profile URL.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            PersonName::validatePair($validator, $this->input('given_name'), $this->input('last_name'));
        });
    }
}
