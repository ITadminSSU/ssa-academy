<?php

namespace Modules\Exam\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportExamTakeoffAnswerKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answer_key_file_url' => 'required|string|max:2048',
            'answer_key_file_name' => 'required|string|max:255',
        ];
    }
}
