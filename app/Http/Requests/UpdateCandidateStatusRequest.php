<?php

namespace App\Http\Requests;

use App\Enums\CandidateStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCandidateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return isAdmin();
    }

    public function rules(): array
    {
        return [
            'candidate_status' => ['required', Rule::enum(CandidateStatus::class)],
            'candidate_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
