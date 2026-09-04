<?php

namespace App\Services\Chat;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class ChatPresenceService
{
    private const CACHE_TTL_SECONDS = 120;

    public function update(User $user, ?int $conversationId, bool $visible): void
    {
        Cache::put($this->cacheKey($user->id), [
            'conversation_id' => $conversationId,
            'visible' => $visible,
            'updated_at' => now()->timestamp,
        ], self::CACHE_TTL_SECONDS);
    }

    public function isOnline(User $user): bool
    {
        return Cache::has($this->cacheKey($user->id));
    }

    public function shouldSendEmail(User $user, int $conversationId): bool
    {
        return ! $this->isViewingConversation($user, $conversationId);
    }

    public function isViewingConversation(User $user, int $conversationId): bool
    {
        $presence = Cache::get($this->cacheKey($user->id));

        if (! is_array($presence)) {
            return false;
        }

        return ($presence['visible'] ?? false)
            && (int) ($presence['conversation_id'] ?? 0) === $conversationId;
    }

    private function cacheKey(int $userId): string
    {
        return "chat:presence:{$userId}";
    }
}
