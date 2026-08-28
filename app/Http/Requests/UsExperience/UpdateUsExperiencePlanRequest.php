<?php

namespace App\Http\Requests\UsExperience;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUsExperiencePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group_name' => 'required|string|max:255',
            'group_description' => 'nullable|string|max:2000',
            'title' => 'required|string|max:255',
            'pass_mark' => 'required|integer|min:1|max:100',
            'max_attempts' => 'required|integer|min:1|max:100',
            'published' => 'required|boolean',
        ];
    }
}
