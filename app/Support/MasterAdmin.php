<?php

namespace App\Support;

use App\Models\User;

class MasterAdmin
{
    public static function userId(): ?int
    {
        static $id = null;

        if ($id !== null) {
            return $id;
        }

        $id = User::query()
            ->where('role', 'admin')
            ->orderBy('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    public static function isProtected(User|int|null $user): bool
    {
        if ($user === null) {
            return false;
        }

        $userId = $user instanceof User ? $user->id : $user;
        $masterId = self::userId();

        return $masterId !== null && $userId === $masterId;
    }
}
