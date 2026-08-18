<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        $allowedTypes = canManagePlatformSettings()
            ? 'admin,operations,employee,trainer'
            : 'employee,trainer';

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'account_type' => ['required', 'in:'.$allowedTypes],
        ];

        if ($this->input('account_type') === 'trainer') {
            $rules['designation'] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'An account with this email already exists.',
            'account_type.in' => 'Please choose a valid account type.',
            'designation.required' => 'Designation is required for trainer accounts.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = (string) $this->input('account_type');

            if (! canManagePlatformSettings() && in_array($type, ['admin', 'operations'], true)) {
                $validator->errors()->add('account_type', 'You do not have permission to create admin accounts.');
            }
        });
    }
}
