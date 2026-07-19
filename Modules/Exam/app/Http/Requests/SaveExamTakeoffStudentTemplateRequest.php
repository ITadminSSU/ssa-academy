<?php

namespace Modules\Exam\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveExamTakeoffStudentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_template_file_url' => 'required|string|max:2048',
            'student_template_file_name' => 'required|string|max:255',
        ];
    }
}
