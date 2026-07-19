<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

class AuthenticatedUser
{
    /**
     * Resolve the acting user id. Client-supplied user_id is only honored for administrators.
     */
    public static function resolve(?int $requestedUserId = null, bool $allowAdminDelegation = false): int
    {
        if ($allowAdminDelegation && isAdmin() && $requestedUserId) {
            return $requestedUserId;
        }

        return (int) Auth::id();
    }
}
