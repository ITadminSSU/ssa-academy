<?php

namespace App\Support;

use App\Models\Setting;

class MailConfigurator
{
    public static function applyFromSetting(?Setting $setting): bool
    {
        if (! $setting instanceof Setting) {
            return false;
        }

        $fields = $setting->fields ?? [];

        if (! is_array($fields) || $fields === []) {
            return false;
        }

        setSmtpConfig($fields);

        if (! empty($fields['mail_password']) && str_starts_with((string) $fields['mail_password'], 're_')) {
            config(['services.resend.key' => $fields['mail_password']]);
        }

        return self::isConfigured();
    }

    public static function isConfigured(): bool
    {
        $mailer = (string) config('mail.default', '');

        if ($mailer === 'resend') {
            return ! empty(config('services.resend.key')) && ! empty(config('mail.from.address'));
        }

        if ($mailer !== 'smtp') {
            return false;
        }

        $required = [
            config('mail.mailers.smtp.host'),
            config('mail.mailers.smtp.port'),
            config('mail.mailers.smtp.username'),
            config('mail.mailers.smtp.password'),
            config('mail.from.address'),
        ];

        foreach ($required as $field) {
            if (empty($field)) {
                return false;
            }
        }

        return true;
    }
}
