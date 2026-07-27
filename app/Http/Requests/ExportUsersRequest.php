<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return isAdmin();
    }

    public function rules(): array
    {
        return [
            'role_filter' => ['required', 'in:external,internal_employee'],
            'search' => ['nullable', 'string', 'max:255'],
            'registered_from' => ['nullable', 'date'],
            'registered_to' => ['nullable', 'date', 'after_or_equal:registered_from'],
        ];
    }
}
