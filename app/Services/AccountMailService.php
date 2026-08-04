<?php

namespace App\Services;

use App\Mail\ChangeEmailVerification;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Support\PasswordResetUrl;
use App\Support\ResendHttpClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AccountMailService
{
    public function sendPasswordResetLink(User $user, string $token): void
    {
        $errors = [];

        if (ResendHttpClient::isAvailable()) {
            try {
                $this->sendPasswordResetViaResendHttp($user, $token);

                return;
            } catch (\Throwable $exception) {
                $errors[] = 'Resend API: '.$exception->getMessage();
                Log::warning('Password reset Resend HTTP failed, trying Laravel Mail', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        try {
            $user->notify(new ResetPasswordNotification($token));

            return;
        } catch (\Throwable $exception) {
            $errors[] = 'Mail: '.$exception->getMessage();
            Log::warning('Password reset Laravel Mail failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);
        }

        throw new \RuntimeException(
            $errors !== [] ? implode(' | ', $errors) : 'Password reset email could not be sent.'
        );
    }

    public function sendChangeEmailVerification(User $user, string $newEmail, string $verificationUrl): void
    {
        $app = app('system_settings');
        $errors = [];

        if (ResendHttpClient::isAvailable()) {
            try {
                $this->sendChangeEmailViaResendHttp($user, $newEmail, $verificationUrl);

                return;
            } catch (\Throwable $exception) {
                $errors[] = 'Resend API: '.$exception->getMessage();
                Log::warning('Change email Resend HTTP failed, trying Laravel Mail', [
                    'user_id' => $user->id,
                    'new_email' => $newEmail,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        try {
            Mail::to($newEmail)->send(new ChangeEmailVerification($user, $app, $verificationUrl));

            return;
        } catch (\Throwable $exception) {
            $errors[] = 'Mail: '.$exception->getMessage();
            Log::warning('Change email Laravel Mail failed', [
                'user_id' => $user->id,
                'new_email' => $newEmail,
                'error' => $exception->getMessage(),
            ]);
        }

        throw new \RuntimeException(
            $errors !== [] ? implode(' | ', $errors) : 'Email change verification could not be sent.'
        );
    }

    private function sendPasswordResetViaResendHttp(User $user, string $token): void
    {
        $url = PasswordResetUrl::forUser($user, $token);

        $html = view('mail.reset-password', [
            'url' => $url,
            'user' => $user,
            'count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
        ])->render();

        ResendHttpClient::send([
            'from' => $this->fromAddress(),
            'to' => [$user->getEmailForPasswordReset()],
            'subject' => 'Reset Password Notification',
            'html' => $html,
        ]);
    }

    private function sendChangeEmailViaResendHttp(User $user, string $newEmail, string $verificationUrl): void
    {
        $html = view('mail.email-change-verification', [
            'user' => $user,
            'verificationUrl' => $verificationUrl,
        ])->render();

        ResendHttpClient::send([
            'from' => $this->fromAddress(),
            'to' => [$newEmail],
            'subject' => 'Changed Email Verification',
            'html' => $html,
        ]);
    }

    private function fromAddress(): string
    {
        return config('mail.from.name').' <'.config('mail.from.address').'>';
    }
}
