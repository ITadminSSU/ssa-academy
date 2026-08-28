<?php

namespace App\Http\Requests\UsExperience;

use Illuminate\Foundation\Http\FormRequest;

class SaveUsExperienceTolerancesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tolerances' => 'required|array',
            'tolerances.*.key' => 'required|string',
            'tolerances.*.tolerance_override' => 'nullable|numeric|min:0',
        ];
    }
}
