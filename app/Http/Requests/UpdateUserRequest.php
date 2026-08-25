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

        if ($this->has('can_manage_platform_settings')) {
            $payload['can_manage_platform_settings'] = filter_var(
                $this->input('can_manage_platform_settings'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ) ?? false;
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

        if ($this->actorCanChangeAccountType($user)) {
            $rules['account_type'] = ['required', 'in:admin,operations,employee,trainer,external,social_media'];

            if ($this->input('account_type') === 'trainer') {
                $rules['designation'] = ['required', 'string', 'max:255'];
            }
        } else {
            if ($user->role === 'student') {
                $rules['user_type'] = ['required', 'in:employee,external'];
            }

            if ($user->role === 'instructor') {
                $rules['designation'] = ['required', 'string', 'max:255'];
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'An account with this email already exists.',
            'account_type.in' => 'Please choose a valid account type.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->resolveUser();

            if ($this->has('account_type') && ! $this->actorCanChangeAccountType($user)) {
                $validator->errors()->add('account_type', 'You do not have permission to change account type.');
            }
        });
    }

    private function actorCanChangeAccountType(User $user): bool
    {
        $actor = $this->user();

        return canManagePlatformSettings()
            && $actor instanceof User
            && (int) $actor->id !== (int) $user->id
            && ! MasterAdmin::isProtected($user);
    }

    private function resolveUser(): User
    {
        return User::findOrFail($this->route('user'));
    }
}
