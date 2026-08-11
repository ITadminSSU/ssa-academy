<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class TwoFactorAuthenticationService
{
    public function __construct(private TotpService $totp) {}

    public function canUse(User $user): bool
    {
        return in_array($user->role, ['admin', 'instructor'], true);
    }

    public function isEnabled(User $user): bool
    {
        return $this->canUse($user)
            && filled($user->two_factor_secret)
            && $user->two_factor_confirmed_at !== null;
    }

    public function beginSetup(User $user): array
    {
        if (! $this->canUse($user)) {
            throw new \RuntimeException('Two-factor authentication is only available for admins and trainers.');
        }

        $secret = $this->totp->generateSecret();
        $issuer = config('app.name', 'SSU Academy');

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return [
            'secret' => $secret,
            'qr_url' => $this->totp->otpAuthUrl($secret, $user->email, $issuer),
        ];
    }

    public function confirmSetup(User $user, string $code): array
    {
        if (! $this->canUse($user) || blank($user->two_factor_secret)) {
            throw new \RuntimeException('Two-factor setup has not been started.');
        }

        $secret = Crypt::decryptString($user->two_factor_secret);

        if (! $this->totp->verify($secret, $code)) {
            throw new \InvalidArgumentException('The authentication code is invalid.');
        }

        $plainCodes = $this->totp->generateRecoveryCodes(10);

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $this->encryptRecoveryHashes($plainCodes),
        ])->save();

        return $plainCodes;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function verifyLoginCode(User $user, string $code): bool
    {
        if (! $this->isEnabled($user)) {
            return false;
        }

        $secret = Crypt::decryptString($user->two_factor_secret);

        if ($this->totp->verify($secret, $code)) {
            return true;
        }

        return $this->consumeRecoveryCode($user, $code);
    }

    public function consumeRecoveryCode(User $user, string $code): bool
    {
        if (blank($user->two_factor_recovery_codes)) {
            return false;
        }

        $normalized = $this->normalizeRecoveryCode($code);
        $hashes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true);

        if (! is_array($hashes) || $normalized === '') {
            return false;
        }

        foreach ($hashes as $index => $hash) {
            if (Hash::check($normalized, $hash)) {
                unset($hashes[$index]);
                $user->forceFill([
                    'two_factor_recovery_codes' => Crypt::encryptString(json_encode(array_values($hashes))),
                ])->save();

                return true;
            }
        }

        return false;
    }

    public function regenerateRecoveryCodes(User $user): array
    {
        if (! $this->isEnabled($user)) {
            throw new \RuntimeException('Two-factor authentication is not enabled.');
        }

        $plainCodes = $this->totp->generateRecoveryCodes(10);

        $user->forceFill([
            'two_factor_recovery_codes' => $this->encryptRecoveryHashes($plainCodes),
        ])->save();

        return $plainCodes;
    }

    private function encryptRecoveryHashes(array $plainCodes): string
    {
        return Crypt::encryptString(json_encode(
            array_map(
                fn (string $code) => Hash::make($this->normalizeRecoveryCode($code)),
                $plainCodes
            )
        ));
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $code) ?? '');
    }
}
