<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/**
 * Phase 1: one active browser session per account.
 * A successful login claims the current session and invalidates all others.
 */
class SingleSessionService
{
    public const KICKED_CACHE_PREFIX = 'single_session.kicked.';

    public const ACTIVE_SESSION_CACHE_PREFIX = 'single_session.active.';

    public function claim(User $user): void
    {
        if (! config('account.single_session.enabled', true)) {
            return;
        }

        $currentSessionId = Session::getId();

        if ($currentSessionId === '') {
            return;
        }

        $this->invalidateOtherDatabaseSessions($user, $currentSessionId);
        $this->rotateRememberToken($user);

        Cache::put(
            $this->activeSessionKey($user->id),
            $currentSessionId,
            now()->addDays(14),
        );
    }

    public function clear(User $user): void
    {
        Cache::forget($this->activeSessionKey($user->id));
    }

    /**
     * Sign the user out of every device. Used when staff disable an account.
     */
    public function revokeAll(User $user): void
    {
        if (config('session.driver') === 'database') {
            $table = config('session.table', 'sessions');
            $sessionIds = DB::table($table)->where('user_id', $user->id)->pluck('id');

            foreach ($sessionIds as $sessionId) {
                Cache::put($this->kickedKey((string) $sessionId), true, now()->addMinutes(45));
            }

            DB::table($table)->where('user_id', $user->id)->delete();
        }

        $this->clear($user);
        $this->rotateRememberToken($user);
    }

    public function isKickedSession(?string $sessionId): bool
    {
        if (! $sessionId) {
            return false;
        }

        return Cache::pull($this->kickedKey($sessionId)) === true;
    }

    public function isActiveSession(User $user, ?string $sessionId): bool
    {
        if (! $sessionId) {
            return false;
        }

        $active = Cache::get($this->activeSessionKey($user->id));

        // No claim recorded yet (legacy sessions before deploy) — allow once.
        if (! is_string($active) || $active === '') {
            Cache::put(
                $this->activeSessionKey($user->id),
                $sessionId,
                now()->addDays(14),
            );

            return true;
        }

        return hash_equals($active, $sessionId);
    }

    public function kickedMessage(): string
    {
        return 'You were signed out because your account was used on another device. Only one active login is allowed per account.';
    }

    private function invalidateOtherDatabaseSessions(User $user, string $currentSessionId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $table = config('session.table', 'sessions');

        $otherSessionIds = DB::table($table)
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->pluck('id');

        foreach ($otherSessionIds as $sessionId) {
            Cache::put($this->kickedKey((string) $sessionId), true, now()->addMinutes(45));
        }

        if ($otherSessionIds->isNotEmpty()) {
            DB::table($table)
                ->where('user_id', $user->id)
                ->where('id', '!=', $currentSessionId)
                ->delete();
        }
    }

    private function rotateRememberToken(User $user): void
    {
        $user->setRememberToken(Str::random(60));
        $user->save();
    }

    private function kickedKey(string $sessionId): string
    {
        return self::KICKED_CACHE_PREFIX.$sessionId;
    }

    private function activeSessionKey(int|string $userId): string
    {
        return self::ACTIVE_SESSION_CACHE_PREFIX.$userId;
    }
}
