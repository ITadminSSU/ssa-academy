<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'questions.*.type' => 'required|in:single,multiple,boolean,quantity_takeoff',
            'questions.*.options' => 'required_unless:questions.*.type,boolean,quantity_takeoff',
            'questions.*.answer' => 'required_unless:questions.*.type,quantity_takeoff',
            'questions.*.pdf_url' => 'required_if:questions.*.type,quantity_takeoff|nullable|string|max:2048',
            'questions.*.pdf_name' => 'required_if:questions.*.type,quantity_takeoff|nullable|string|max:255',
            'questions.*.answer_key_url' => 'required_if:questions.*.type,quantity_takeoff|nullable|string|max:2048',
            'questions.*.answer_key_name' => 'required_if:questions.*.type,quantity_takeoff|nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'questions.required' => 'Add at least one question before saving.',
            'questions.min' => 'Add at least one question before saving.',
            'questions.*.title.required' => 'Question text is required.',
            'questions.*.type.in' => 'Question type must be single, multiple, true/false, or quantity takeoff.',
            'questions.*.options.required_unless' => 'Options are required for this question type.',
            'questions.*.answer.required_unless' => 'Answer is required.',
            'questions.*.pdf_url.required_if' => 'Upload the takeoff PDF plans.',
            'questions.*.answer_key_url.required_if' => 'Upload the Excel answer key.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $questions = $this->input('questions', []);
            $types = array_column($questions, 'type');

            if (in_array('quantity_takeoff', $types, true) && count($questions) !== 1) {
                $validator->errors()->add('questions', 'A quantity takeoff quiz can only contain that one question.');
            }
        });
    }
}
