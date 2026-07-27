<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendEmailVerificationNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId,
        public bool $autoVerifyOnFailure = true,
    ) {}

    public function handle(): void
    {
        $user = User::query()->find($this->userId);

        if (! $user || $user->hasVerifiedEmail()) {
            return;
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            Log::warning('Email verification notification failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);

            if ($this->autoVerifyOnFailure) {
                $user->forceFill(['email_verified_at' => now()])->save();

                Log::info('User auto-verified after email verification failure', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            }
        }
    }
}
