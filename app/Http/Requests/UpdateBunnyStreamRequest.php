<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBunnyStreamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enabled' => 'required|boolean',
            'library_id' => 'required_if:enabled,true|nullable|string|max:64',
            'api_key' => 'required_if:enabled,true|nullable|string|max:255',
            'cdn_hostname' => 'nullable|string|max:255',
            'token_auth_key' => 'nullable|string|max:255',
        ];
    }
}
