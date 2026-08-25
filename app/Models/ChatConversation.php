<?php

namespace App\Models;

use App\Enums\ChatConversationType;
use App\Models\Course\Course;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    protected $fillable = [
        'type',
        'course_id',
        'student_user_id',
        'title',
        'last_message_at',
        'resolved_at',
        'resolved_by',
        'pinned_message_id',
    ];

    protected $casts = [
        'type' => ChatConversationType::class,
        'last_message_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ChatParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->latest('id');
    }

    public function pinnedMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'pinned_message_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
