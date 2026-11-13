<?php

namespace App\Http\Requests;

use App\Support\PersonName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateStudentProfileRequest extends FormRequest
{
    /** 5 GB, in kilobytes. Laravel's file max rule uses kilobytes. */
    public const PROFILE_DOCUMENT_MAX_KILOBYTES = 5 * 1024 * 1024;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('remove_photo')) {
            $this->merge([
                'remove_photo' => filter_var($this->input('remove_photo'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->has('facebook')) {
            $facebook = trim((string) $this->input('facebook'));
            $this->merge([
                'facebook' => $facebook === '' ? null : $facebook,
            ]);
        }

        if ($this->has('has_us_estimating_experience')) {
            $this->merge([
                'has_us_estimating_experience' => filter_var(
                    $this->input('has_us_estimating_experience'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ),
            ]);
        }

        foreach (['remove_cv', 'remove_id_document', 'remove_criteria_assessment'] as $flag) {
            if ($this->has($flag)) {
                $this->merge([
                    $flag => filter_var($this->input($flag), FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'given_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:15360',
            'remove_photo' => 'nullable|boolean',
            'facebook' => ['nullable', 'string', 'max:255', 'url'],
            'has_us_estimating_experience' => 'nullable|boolean',
            'cv_resume' => 'nullable|file|mimes:pdf,doc,docx|max:'.self::PROFILE_DOCUMENT_MAX_KILOBYTES,
            'id_document' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:'.self::PROFILE_DOCUMENT_MAX_KILOBYTES,
            'criteria_assessment' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png,webp|max:'.self::PROFILE_DOCUMENT_MAX_KILOBYTES,
            'remove_cv' => 'nullable|boolean',
            'remove_id_document' => 'nullable|boolean',
            'remove_criteria_assessment' => 'nullable|boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'given_name.required' => 'Please enter your given name.',
            'facebook.url' => 'Enter a valid Facebook profile URL.',
            'cv_resume.mimes' => 'CV / resume must be a PDF, DOC, or DOCX file.',
            'cv_resume.max' => 'CV / resume must not be larger than 5 GB.',
            'id_document.mimes' => 'ID must be a JPG, PNG, WEBP, or PDF file.',
            'id_document.max' => 'ID must not be larger than 5 GB.',
            'criteria_assessment.mimes' => 'Assessment result must be a PDF, DOC, DOCX, JPG, PNG, or WEBP file.',
            'criteria_assessment.max' => 'Assessment result must not be larger than 5 GB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            PersonName::validatePair($validator, $this->input('given_name'), $this->input('last_name'));
        });
    }
}
