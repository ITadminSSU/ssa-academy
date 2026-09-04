<?php

namespace App\Notifications;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ChatMessageNotification extends Notification
{
    public function __construct(
        private ChatConversation $conversation,
        private ChatMessage $message,
        private string $label,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $preview = filled($this->message->body)
            ? mb_strimwidth(strip_tags((string) $this->message->body), 0, 120, '…')
            : 'New attachment';

        return [
            'title' => 'Message: '.$this->label,
            'body' => ($this->message->sender?->name ? $this->message->sender->name.': ' : '').$preview,
            'url' => route('messages.show', $this->conversation),
            'type' => 'chat_message',
            'conversation_id' => $this->conversation->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
