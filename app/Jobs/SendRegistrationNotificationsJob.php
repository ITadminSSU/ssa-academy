<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\LegalAgreementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendRegistrationNotificationsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId,
    ) {}

    public function handle(LegalAgreementService $legalAgreement): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            Log::warning('Email verification failed during registration, auto-verifying user', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
        }

        try {
            $legalAgreement->sendAcceptanceEmail($user);
        } catch (\Throwable $exception) {
            Log::warning('Failed to send legal agreement confirmation email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
