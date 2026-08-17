<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class EmailVerificationCodeService
{
    public function expireMinutes(): int
    {
        return max(5, (int) config('auth.verification.expire', 15));
    }

    public function maxAttempts(): int
    {
        return max(1, (int) config('auth.verification.max_attempts', 5));
    }

    public function resendCooldownSeconds(): int
    {
        return max(15, (int) config('auth.verification.resend_cooldown', 60));
    }

    public function issue(User $user): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->forceFill([
            'email_verification_code_hash' => Hash::make($code),
            'email_verification_expires_at' => now()->addMinutes($this->expireMinutes()),
            'email_verification_attempts' => 0,
        ])->save();

        return $code;
    }

    public function markSent(User $user): void
    {
        RateLimiter::hit($this->resendKey($user), $this->resendCooldownSeconds());
    }

    public function hasLiveCode(User $user): bool
    {
        return filled($user->email_verification_code_hash)
            && $user->email_verification_expires_at !== null
            && $user->email_verification_expires_at->isFuture()
            && (int) $user->email_verification_attempts < $this->maxAttempts();
    }

    public function resendAvailableIn(User $user): int
    {
        if (! RateLimiter::tooManyAttempts($this->resendKey($user), 1)) {
            return 0;
        }

        return RateLimiter::availableIn($this->resendKey($user));
    }

    public function canResend(User $user): bool
    {
        return $this->resendAvailableIn($user) === 0;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function attempt(User $user, string $code): array
    {
        $digits = preg_replace('/\D/', '', $code) ?? '';

        if (strlen($digits) !== 6) {
            return ['ok' => false, 'message' => 'Enter the 6-digit code from your email.'];
        }

        if (! filled($user->email_verification_code_hash) || $user->email_verification_expires_at === null) {
            return ['ok' => false, 'message' => 'Request a new code, then try again.'];
        }

        if ($user->email_verification_expires_at->isPast()) {
            $this->clear($user);

            return ['ok' => false, 'message' => 'That code has expired. Request a new one.'];
        }

        if ((int) $user->email_verification_attempts >= $this->maxAttempts()) {
            $this->clear($user);

            return ['ok' => false, 'message' => 'Too many attempts. Request a new code.'];
        }

        if (! Hash::check($digits, $user->email_verification_code_hash)) {
            $attempts = (int) $user->email_verification_attempts + 1;
            $user->forceFill(['email_verification_attempts' => $attempts])->save();

            if ($attempts >= $this->maxAttempts()) {
                $this->clear($user);

                return ['ok' => false, 'message' => 'Too many attempts. Request a new code.'];
            }

            $left = $this->maxAttempts() - $attempts;

            return ['ok' => false, 'message' => "That code is incorrect. {$left} attempts left."];
        }

        $this->clear($user);

        return ['ok' => true, 'message' => ''];
    }

    public function clear(User $user): void
    {
        $user->forceFill([
            'email_verification_code_hash' => null,
            'email_verification_expires_at' => null,
            'email_verification_attempts' => 0,
        ])->save();
    }

    private function resendKey(User $user): string
    {
        return 'email-verification-resend:'.$user->id;
    }
}
