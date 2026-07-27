<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\LegalAgreementService;
use App\Services\SettingsService;
use App\Support\MailConfigurator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendRegistrationNotificationsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var array<int, int> */
    public array $backoff = [15, 60, 180];

    public function __construct(
        public int $userId,
    ) {}

    public function handle(LegalAgreementService $legalAgreement, SettingsService $settingsService): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        if ($user->legal_confirmation_email_sent_at) {
            return;
        }

        $smtpSetting = $settingsService->getSetting(['type' => 'smtp']);
        $fields = $smtpSetting?->fields ?? [];

        MailConfigurator::applyFromSetting($smtpSetting);

        $apiKey = is_array($fields) ? ($fields['mail_password'] ?? null) : null;

        $legalAgreement->deliverAcceptanceEmail($user, resendApiKey: is_string($apiKey) ? $apiKey : null);

        Log::info('Legal agreement confirmation email sent after registration', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $message = $exception?->getMessage() ?? 'Unknown mail queue failure';

        Log::error('Registration legal email failed after all retries', [
            'user_id' => $user->id,
            'email' => $user->email,
            'error' => $message,
        ]);
    }
}
