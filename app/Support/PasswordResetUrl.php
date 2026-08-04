<?php

namespace App\Support;

use App\Models\User;

class PasswordResetUrl
{
    public static function forUser(User $user, string $token): string
    {
        $email = $user->getEmailForPasswordReset();

        return url('/password-reset/'.$token.'?email='.rawurlencode($email));
    }
}
