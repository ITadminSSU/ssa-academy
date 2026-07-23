<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

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
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'account_type' => ['required', 'in:admin,employee,trainer'],
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
}
