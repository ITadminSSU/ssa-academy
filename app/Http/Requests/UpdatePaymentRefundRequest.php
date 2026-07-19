<?php

namespace App\Http\Requests;

use App\Enums\PaymentRefundStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return isAdmin();
    }

    public function rules(): array
    {
        return [
            'refund_status' => ['required', Rule::enum(PaymentRefundStatus::class)],
            'refund_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
