<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkStoreQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section_quiz_id' => 'required|exists:section_quizzes,id',
            'questions' => 'required|array|min:1',
            'questions.*.title' => 'required|string|max:255',
            'questions.*.type' => 'required|in:single,multiple,boolean',
            'questions.*.options' => 'required_unless:questions.*.type,boolean',
            'questions.*.answer' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'questions.required' => 'Add at least one question before saving.',
            'questions.min' => 'Add at least one question before saving.',
            'questions.*.title.required' => 'Question text is required.',
            'questions.*.type.in' => 'Question type must be single, multiple, or boolean.',
            'questions.*.options.required_unless' => 'Options are required for this question type.',
            'questions.*.answer.required' => 'Answer is required.',
        ];
    }
}
