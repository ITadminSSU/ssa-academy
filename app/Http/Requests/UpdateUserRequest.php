<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\MasterAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('status')) {
            $payload['status'] = (int) $this->input('status');
        }

        if ($this->has('email')) {
            $payload['email'] = strtolower(trim((string) $this->input('email')));
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        $user = $this->resolveUser();

        if (MasterAdmin::isProtected($user)) {
            return [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            ];
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'status' => ['required', 'in:0,1'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];

        if ($user->role === 'student') {
            $rules['user_type'] = ['required', 'in:employee,external'];
        }

        if ($user->role === 'instructor') {
            $rules['designation'] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'An account with this email already exists.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        //
    }

    private function resolveUser(): User
    {
        return User::findOrFail($this->route('user'));
    }
}
