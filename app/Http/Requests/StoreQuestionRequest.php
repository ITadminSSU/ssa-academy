<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionRequest extends FormRequest
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
            'pdf_url' => $isTakeoff ? 'required|string|max:2048' : 'nullable',
            'pdf_name' => $isTakeoff ? 'required|string|max:255' : 'nullable',
            'answer_key_url' => $isTakeoff ? 'required|string|max:2048' : 'nullable',
            'answer_key_name' => $isTakeoff ? 'required|string|max:255' : 'nullable',
        ];
    }
}
