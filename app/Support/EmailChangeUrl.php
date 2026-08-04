<?php

namespace App\Support;

use App\Models\User;

class EmailChangeUrl
{
    public static function verificationLink(User $user, string $token): string
    {
        return route('account.save-email', [
            'user' => $user->id,
            'token' => $token,
        ]);
    }
}
