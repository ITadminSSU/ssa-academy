<?php

namespace App\Http\Requests\UsExperience;

use Illuminate\Foundation\Http\FormRequest;

class SubmitUsExperienceAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'takeoff_pdf_url' => 'required|string|max:2048',
            'takeoff_pdf_name' => 'required|string|max:255',
            'boq_xlsx_url' => 'required|string|max:2048',
            'boq_xlsx_name' => 'required|string|max:255',
        ];
    }
}
