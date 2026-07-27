<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\LegalAgreementService;
use App\Services\SettingsService;
use App\Support\MailConfigurator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendRegistrationNotificationsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId,
    ) {}

    public function handle(LegalAgreementService $legalAgreement, SettingsService $settingsService): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $smtpSetting = $settingsService->getSetting(['type' => 'smtp']);

        if (! MailConfigurator::applyFromSetting($smtpSetting)) {
            Log::warning('Registration legal email skipped: mail is not configured', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return;
        }

        try {
            $legalAgreement->sendAcceptanceEmail($user);

            Log::info('Legal agreement confirmation email sent after registration', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Failed to send legal agreement confirmation email after registration', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
