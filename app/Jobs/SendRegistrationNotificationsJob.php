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

    public int $tries = 5;

    public int $timeout = 120;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120, 300, 600];

    public function __construct(
        public int $userId,
    ) {
        $this->onQueue('mail');
    }

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

        if (! MailConfigurator::applyFromSetting($smtpSetting)) {
            throw new \RuntimeException('Mail is not configured. Set Admin → SMTP to Resend API before launch.');
        }

        $legalAgreement->sendAcceptanceEmail($user);

        $user->forceFill([
            'legal_confirmation_email_sent_at' => now(),
            'legal_confirmation_email_last_error' => null,
        ])->save();

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

        $user->forceFill([
            'legal_confirmation_email_last_error' => $message,
        ])->save();

        Log::error('Registration legal email failed after all retries', [
            'user_id' => $user->id,
            'email' => $user->email,
            'error' => $message,
        ]);
    }
}
