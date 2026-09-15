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
            'takeoff_pdf_name' => 'required|string|max:255|regex:/\.pdf$/i',
            'boq_xlsx_url' => 'required|string|max:2048',
            'boq_xlsx_name' => 'required|string|max:255|regex:/\.xlsx$/i',
        ];
    }

    public function messages(): array
    {
        return [
            'takeoff_pdf_name.regex' => 'The takeoff file must be a PDF.',
            'boq_xlsx_name.regex' => 'The Excel BOQ must be an .xlsx file.',
        ];
    }
}
