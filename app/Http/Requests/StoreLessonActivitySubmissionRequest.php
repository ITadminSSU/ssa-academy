<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLessonActivitySubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section_lesson_id' => 'required|exists:section_lessons,id',
            'attachment_type' => 'required|in:url,file',
            'attachment_path' => 'required|string',
            'comment' => 'nullable|string',
        ];
    }
}
