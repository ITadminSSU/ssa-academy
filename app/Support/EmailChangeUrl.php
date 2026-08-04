<?php

namespace App\Support;

use App\Models\User;

class EmailChangeUrl
{
    public static function verificationLink(User $user, string $token): string
    {
        return url('/confirm-email-change', [
            'user' => $user->id,
            'token' => $token,
        ]);
    }
}
