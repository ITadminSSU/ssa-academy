<?php

namespace App\Services;

use App\Mail\ChangeEmailAlert;
use App\Mail\ChangeEmailVerification;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Services\Auth\EmailVerificationCodeService;
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

    public function sendEmailVerification(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $codes = app(EmailVerificationCodeService::class);
        $code = $codes->issue($user);
        $errors = [];

        if (ResendHttpClient::isAvailable()) {
            try {
                $this->sendEmailVerificationViaResendHttp($user, $code);
                $codes->markSent($user);

                return;
            } catch (\Throwable $exception) {
                $errors[] = 'Resend API: '.$exception->getMessage();
                Log::warning('Email verification Resend HTTP failed, trying Laravel Mail', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        try {
            $user->notify(new VerifyEmailNotification($code));
            $codes->markSent($user);

            return;
        } catch (\Throwable $exception) {
            $errors[] = 'Mail: '.$exception->getMessage();
            Log::warning('Email verification Laravel Mail failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);
        }

        throw new \RuntimeException(
            $errors !== [] ? implode(' | ', $errors) : 'Email verification could not be sent.'
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

    /**
     * Alert the current (old) email that a change was requested. Best-effort; failures are logged only.
     */
    public function sendChangeEmailAlert(User $user, string $newEmail): void
    {
        $oldEmail = $user->email;

        if ($oldEmail === '' || strcasecmp($oldEmail, $newEmail) === 0) {
            return;
        }

        try {
            if (ResendHttpClient::isAvailable()) {
                $this->sendChangeEmailAlertViaResendHttp($user, $oldEmail, $newEmail);

                return;
            }

            Mail::to($oldEmail)->send(new ChangeEmailAlert($user, $newEmail));
        } catch (\Throwable $exception) {
            Log::warning('Change email alert to old address failed', [
                'user_id' => $user->id,
                'old_email' => $oldEmail,
                'new_email' => $newEmail,
                'error' => $exception->getMessage(),
            ]);
        }
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

    private function sendEmailVerificationViaResendHttp(User $user, string $code): void
    {
        $expireMinutes = app(EmailVerificationCodeService::class)->expireMinutes();

        $html = view('mail.email-verification', [
            'user' => $user,
            'code' => $code,
            'expireMinutes' => $expireMinutes,
        ])->render();

        $text = view('mail.email-verification-text', [
            'user' => $user,
            'code' => $code,
            'expireMinutes' => $expireMinutes,
        ])->render();

        $appName = config('branding.short_name', config('app.name'));

        ResendHttpClient::send([
            'from' => $this->fromAddress(),
            'to' => [$user->getEmailForVerification()],
            'subject' => "Your verification code — {$appName}",
            'html' => $html,
            'text' => $text,
        ]);
    }

    private function sendChangeEmailViaResendHttp(User $user, string $newEmail, string $verificationUrl): void
    {
        $html = view('mail.email-change-verification', [
            'user' => $user,
            'verificationUrl' => $verificationUrl,
        ])->render();

        $text = view('mail.email-change-verification-text', [
            'user' => $user,
            'verificationUrl' => $verificationUrl,
        ])->render();

        $appName = config('branding.short_name', config('app.name'));

        ResendHttpClient::send([
            'from' => $this->fromAddress(),
            'to' => [$newEmail],
            'subject' => "Confirm your new email for {$appName}",
            'html' => $html,
            'text' => $text,
        ]);
    }

    private function sendChangeEmailAlertViaResendHttp(User $user, string $oldEmail, string $newEmail): void
    {
        $html = view('mail.email-change-alert', [
            'user' => $user,
            'newEmail' => $newEmail,
        ])->render();

        $text = view('mail.email-change-alert-text', [
            'user' => $user,
            'newEmail' => $newEmail,
        ])->render();

        $appName = config('branding.short_name', config('app.name'));

        ResendHttpClient::send([
            'from' => $this->fromAddress(),
            'to' => [$oldEmail],
            'subject' => "Email change requested for your {$appName} account",
            'html' => $html,
            'text' => $text,
        ]);
    }

    private function fromAddress(): string
    {
        return config('mail.from.name').' <'.config('mail.from.address').'>';
    }
}
