<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AccountMailService;
use App\Services\SettingsService;
use App\Support\MailConfigurator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendEmailVerificationNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60, 180];

    public function __construct(
        public int $userId,
    ) {}

    public function handle(AccountMailService $mail, SettingsService $settingsService): void
    {
        $user = User::query()->find($this->userId);

        if (! $user || $user->hasVerifiedEmail()) {
            return;
        }

        MailConfigurator::applyFromSetting($settingsService->getSetting(['type' => 'smtp']));

        $mail->sendEmailVerification($user);

        Log::info('Email verification link sent', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Email verification send failed after all retries — user was NOT auto-verified', [
            'user_id' => $this->userId,
            'error' => $exception?->getMessage() ?? 'Unknown mail failure',
        ]);
    }
}
