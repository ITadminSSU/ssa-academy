<?php

namespace App\Http\Requests;

use App\Support\AuthenticatedUser;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuizTakeoffSubmissionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => AuthenticatedUser::resolve(
                $this->input('user_id') ? (int) $this->input('user_id') : null,
            ),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'section_quiz_id' => 'required|exists:section_quizzes,id',
            'user_id' => 'required|exists:users,id',
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
