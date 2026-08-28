<?php

namespace App\Http\Requests\UsExperience;

use Illuminate\Foundation\Http\FormRequest;

class SaveUsExperienceUploadedFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file_url' => 'required|string|max:2048',
            'file_name' => 'required|string|max:255',
        ];
    }
}
