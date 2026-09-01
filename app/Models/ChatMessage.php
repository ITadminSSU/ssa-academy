<?php

namespace App\Models;

use App\Enums\ChatAttachmentType;
use App\Support\S3CompatibleStorage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ChatMessage extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'chat_conversation_id',
        'user_id',
        'body',
        'attachment',
        'attachment_name',
        'attachment_type',
    ];

    protected $casts = [
        'attachment_type' => ChatAttachmentType::class,
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function attachment(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? S3CompatibleStorage::attributeGet($value) : null,
            set: fn (?string $value) => $value ? S3CompatibleStorage::attributeSet($value) : null,
        );
    }
}
