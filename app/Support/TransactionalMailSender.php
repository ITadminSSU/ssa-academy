<?php

namespace App\Support;

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TransactionalMailSender
{
    public function __construct(
        private SettingsService $settings,
    ) {}

    /**
     * @param  list<string>  $bcc
     */
    public function send(User $user, Mailable $mailable, string $logContext, array $bcc = []): bool
    {
        if (! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            Log::warning("{$logContext} skipped: invalid recipient", [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return false;
        }

        $smtpSetting = $this->settings->getSetting(['type' => 'smtp']);
        $fields = $smtpSetting?->fields ?? [];
        $apiKey = is_array($fields) ? ($fields['mail_password'] ?? null) : null;

        if (is_string($apiKey) && str_starts_with($apiKey, 're_')) {
            config(['services.resend.key' => $apiKey]);
        }

        MailConfigurator::applyFromSetting($smtpSetting);

        $subject = $mailable->envelope()->subject;

        if (ResendHttpClient::isAvailable()) {
            try {
                $payload = [
                    'from' => config('mail.from.name').' <'.config('mail.from.address').'>',
                    'to' => [$user->email],
                    'subject' => $subject,
                    'html' => $mailable->render(),
                ];

                if ($bcc !== []) {
                    $payload['bcc'] = $bcc;
                }

                ResendHttpClient::send($payload);

                return true;
            } catch (\Throwable $exception) {
                Log::warning("{$logContext} Resend HTTP failed, trying Laravel Mail", [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'subject' => $subject,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if (! MailConfigurator::isConfigured()) {
            Log::warning("{$logContext} skipped: mail not configured", [
                'user_id' => $user->id,
                'email' => $user->email,
                'subject' => $subject,
            ]);

            return false;
        }

        try {
            $pending = Mail::to($user->email);
            if ($bcc !== []) {
                $pending->bcc($bcc);
            }
            $pending->send($mailable);

            return true;
        } catch (\Throwable $exception) {
            Log::warning("{$logContext} failed", [
                'user_id' => $user->id,
                'email' => $user->email,
                'subject' => $subject,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
