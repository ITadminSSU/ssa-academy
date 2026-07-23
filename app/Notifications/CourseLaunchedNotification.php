<?php

namespace App\Notifications;

use App\Models\Course\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CourseLaunchedNotification extends Notification
{
    use Queueable;

    public function __construct(private Course $course) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $siteName = config('app.name');
        $url = route('course.details', [
            'slug' => $this->course->slug,
            'id' => $this->course->id,
        ]);

        return (new MailMessage)
            ->subject("{$this->course->title} is now available on {$siteName}")
            ->greeting('Good news!')
            ->line("The course **{$this->course->title}** you asked to be notified about is now open.")
            ->action('View course', $url)
            ->line('We hope you enjoy learning with us.');
    }
}
