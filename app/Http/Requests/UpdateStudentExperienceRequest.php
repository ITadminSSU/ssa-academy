<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentExperienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('has_us_estimating_experience')) {
            $this->merge([
                'has_us_estimating_experience' => filter_var(
                    $this->input('has_us_estimating_experience'),
                    FILTER_VALIDATE_BOOLEAN,
                ),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'has_us_estimating_experience' => 'required|boolean',
        ];
    }
}
