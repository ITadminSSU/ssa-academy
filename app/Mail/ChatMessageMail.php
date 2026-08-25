<?php

namespace App\Mail;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChatMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ChatConversation $conversation,
        public ChatMessage $message,
    ) {}

    public function envelope(): Envelope
    {
        $course = $this->conversation->course?->title ?? 'Course';
        $siteName = (string) config('app.name');

        return new Envelope(
            subject: "New message — {$course} — {$siteName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.chat-message',
            with: [
                'conversation' => $this->conversation,
                'message' => $this->message,
                'messagesUrl' => route('messages.show', $this->conversation),
                'siteName' => (string) config('app.name'),
            ],
        );
    }
}
