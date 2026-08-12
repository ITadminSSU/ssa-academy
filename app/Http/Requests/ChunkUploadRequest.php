<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ChunkUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'part_number' => 'required|integer|min:1',
            'filename' => 'required|string',
            'mimetype' => 'required|string',
            // Prefer binary multipart uploads; keep base64 for older clients.
            'chunk' => 'nullable|file',
            'chunk_data' => 'nullable|string',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->hasFile('chunk') && blank($this->input('chunk_data'))) {
                $validator->errors()->add('chunk', 'A chunk file or chunk_data payload is required.');
            }
        });
    }
}
