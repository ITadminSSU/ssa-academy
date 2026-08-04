<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\URL;

class EmailChangeUrl
{
    public static function verificationLink(User $user, string $token): string
    {
        $expiryMinutes = (int) config('account.email_change_token_expiry_minutes', 60);

        return URL::temporarySignedRoute(
            'account.save-email',
            now()->addMinutes($expiryMinutes),
            [
                'user' => $user->id,
                'token' => $token,
            ],
        );
    }
}
