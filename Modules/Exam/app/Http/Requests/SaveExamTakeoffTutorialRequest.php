<?php

namespace Modules\Exam\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveExamTakeoffTutorialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tutorial_video_url' => 'required|string|max:2048',
            'tutorial_video_name' => 'required|string|max:255',
        ];
    }
}
