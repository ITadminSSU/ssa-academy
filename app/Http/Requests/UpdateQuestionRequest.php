<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isTakeoff = $this->input('type') === 'quantity_takeoff';

        return [
            'title' => 'required|string|max:255',
            'type' => 'required|in:single,multiple,boolean,quantity_takeoff',
            'options' => $isTakeoff ? 'nullable' : 'required_unless:type,boolean',
            'answer' => $isTakeoff ? 'nullable' : 'required',
            'sort' => 'required|integer',
            'section_quiz_id' => 'required|exists:section_quizzes,id',
            'pdf_url' => 'nullable|string|max:2048',
            'pdf_name' => 'nullable|string|max:255',
            'answer_key_url' => 'nullable|string|max:2048',
            'answer_key_name' => 'nullable|string|max:255',
        ];
    }
}
