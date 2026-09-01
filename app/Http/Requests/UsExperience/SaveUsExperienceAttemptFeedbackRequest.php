<?php

namespace App\Http\Requests\UsExperience;

use Illuminate\Foundation\Http\FormRequest;

class SaveUsExperienceAttemptFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trainer_feedback' => 'nullable|string|max:5000',
        ];
    }
}
