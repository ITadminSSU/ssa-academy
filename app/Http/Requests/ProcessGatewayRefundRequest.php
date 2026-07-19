<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessGatewayRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return isAdmin();
    }

    public function rules(): array
    {
        return [
            'confirmed' => ['required', 'accepted'],
            'refund_notes' => ['nullable', 'string', 'max:5000'],
            'payment_id' => ['nullable', 'integer', 'exists:payment_histories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmed.accepted' => 'You must confirm this irreversible refund action.',
        ];
    }
}
