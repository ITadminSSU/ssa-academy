<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class AnnouncementNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Announcement $announcement,
        private string $url,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New announcement: '.$this->announcement->title,
            'body' => Str::limit(strip_tags($this->announcement->body), 200),
            'url' => $this->url,
            'announcement_id' => $this->announcement->id,
        ];
    }
}
