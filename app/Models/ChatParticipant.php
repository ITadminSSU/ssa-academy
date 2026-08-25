<?php

namespace App\Models;

use App\Enums\ChatParticipantRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatParticipant extends Model
{
    protected $fillable = [
        'chat_conversation_id',
        'user_id',
        'role',
        'last_read_at',
        'is_active',
        'is_muted',
    ];

    protected $casts = [
        'role' => ChatParticipantRole::class,
        'last_read_at' => 'datetime',
        'is_active' => 'boolean',
        'is_muted' => 'boolean',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
