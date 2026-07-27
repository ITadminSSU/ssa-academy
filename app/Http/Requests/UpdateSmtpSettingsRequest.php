<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSmtpSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $mailer = (string) $this->input('mail_mailer', 'smtp');

        return [
            'mail_mailer' => ['required', 'string', Rule::in(['smtp', 'resend'])],
            'mail_host' => [Rule::requiredIf($mailer === 'smtp'), 'nullable', 'string', 'max:255'],
            'mail_port' => [Rule::requiredIf($mailer === 'smtp'), 'nullable', 'numeric', 'between:1,65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['required', 'string'],
            'mail_encryption' => ['nullable', Rule::in(['tls', 'ssl', ''])],
            'mail_from_address' => ['required', 'email', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'mail_mailer.required' => 'Mail driver is required',
            'mail_mailer.in' => 'Selected mail driver is invalid',
            'mail_host.required' => 'SMTP host is required when using the SMTP driver',
            'mail_port.required' => 'SMTP port is required when using the SMTP driver',
            'mail_port.numeric' => 'SMTP port must be a number',
            'mail_port.between' => 'SMTP port must be between 1 and 65535',
            'mail_encryption.in' => 'Encryption type must be TLS, SSL, or none',
            'mail_from_address.required' => 'From address is required',
            'mail_from_address.email' => 'From address must be a valid email',
            'mail_from_name.required' => 'From name is required',
            'mail_password.required' => 'Mail password / API key is required',
        ];
    }
}
