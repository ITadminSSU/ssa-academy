<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
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
            $chunk = $this->file('chunk');

            if ($chunk instanceof UploadedFile && ! $chunk->isValid()) {
                $validator->errors()->add('chunk', $this->uploadErrorMessage($chunk));

                return;
            }

            if (! $this->hasFile('chunk') && blank($this->input('chunk_data'))) {
                $uploadMax = ini_get('upload_max_filesize') ?: 'unknown';
                $postMax = ini_get('post_max_size') ?: 'unknown';

                $validator->errors()->add(
                    'chunk',
                    "Chunk upload missing. On Forge set PHP upload_max_filesize and post_max_size to at least 20M (current: upload_max_filesize={$uploadMax}, post_max_size={$postMax}), and Nginx client_max_body_size 20M."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'chunk.file' => 'The chunk failed to upload. Raise Forge PHP upload_max_filesize/post_max_size and Nginx client_max_body_size to at least 20M.',
        ];
    }

    private function uploadErrorMessage(UploadedFile $file): string
    {
        $uploadMax = ini_get('upload_max_filesize') ?: 'unknown';
        $postMax = ini_get('post_max_size') ?: 'unknown';

        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "Chunk exceeds PHP upload limit. In Forge PHP set upload_max_filesize=20M and post_max_size=20M (current: {$uploadMax} / {$postMax}).",
            UPLOAD_ERR_PARTIAL => 'Chunk was only partially uploaded. Please retry.',
            UPLOAD_ERR_NO_FILE => 'No chunk file was received by PHP.',
            default => 'The chunk failed to upload (PHP error '.$file->getError().'). Check Forge PHP upload limits (need >= 20M).',
        };
    }
}
