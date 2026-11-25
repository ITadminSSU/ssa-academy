<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncStudentLabelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_label_ids' => ['present', 'array'],
            'student_label_ids.*' => ['integer', 'distinct', 'exists:student_labels,id'],
        ];
    }
}
