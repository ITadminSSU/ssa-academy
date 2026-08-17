<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\URL;

class EmailVerificationUrl
{
    public static function expireMinutes(): int
    {
        return max(60, (int) config('auth.verification.expire', 1440));
    }

    public static function forUser(User $user): string
    {
        $root = rtrim((string) config('app.url'), '/');

        if ($root !== '') {
            URL::forceRootUrl($root);

            if (str_starts_with($root, 'https://')) {
                URL::forceScheme('https');
            }
        }

        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(self::expireMinutes()),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );
    }
}
