<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentProfileDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        $max = UpdateStudentProfileRequest::PROFILE_DOCUMENT_MAX_KILOBYTES;

        return match ($this->route('type')) {
            'cv' => [
                'cv_resume' => 'required|file|mimes:pdf,doc,docx|max:'.$max,
            ],
            'id' => [
                'id_document' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:'.$max,
            ],
            'assessment' => [
                'criteria_assessment' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png,webp|max:'.$max,
            ],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cv_resume.required' => 'Please choose a CV or resume.',
            'cv_resume.mimes' => 'CV / resume must be a PDF, DOC, or DOCX file.',
            'cv_resume.max' => 'CV / resume must not be larger than 5 GB.',
            'id_document.required' => 'Please choose a government ID.',
            'id_document.mimes' => 'ID must be a JPG, PNG, WEBP, or PDF file.',
            'id_document.max' => 'ID must not be larger than 5 GB.',
            'criteria_assessment.required' => 'Please choose an assessment result.',
            'criteria_assessment.mimes' => 'Assessment result must be a PDF, DOC, DOCX, JPG, PNG, or WEBP file.',
            'criteria_assessment.max' => 'Assessment result must not be larger than 5 GB.',
        ];
    }
}
