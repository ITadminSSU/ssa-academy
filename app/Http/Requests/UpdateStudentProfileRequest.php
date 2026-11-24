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

        $referredBy = trim((string) $this->input('referred_by'));

        if ($referredBy === '') {
            $this->merge([
                'referred_by' => null,
                'referrer_is_employee' => null,
            ]);
        } elseif ($this->input('referrer_is_employee') === '') {
            $this->merge([
                'referrer_is_employee' => null,
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
            'referred_by' => ['nullable', 'string', 'max:255'],
            'referrer_is_employee' => ['nullable', 'boolean'],
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

            $referredBy = trim((string) $this->input('referred_by'));

            if ($referredBy !== '' && $this->input('referrer_is_employee') === null) {
                $validator->errors()->add('referrer_is_employee', 'Say whether this person is a Smart Sourcing employee.');
            }
        });
    }
}
