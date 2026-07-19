<?php

namespace Modules\Exam\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveExamTakeoffTolerancesRequest extends FormRequest
{
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
            'tolerances' => 'required|array',
            'tolerances.*.key' => 'required|string',
            'tolerances.*.tolerance_override' => 'nullable|numeric|min:0',
        ];
    }
}
