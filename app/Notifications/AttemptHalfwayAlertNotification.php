<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AttemptHalfwayAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $student,
        public string $activityType,
        public int $activityId,
        public string $activityTitle,
        public int $attemptsUsed,
        public int $maxAttempts,
        public string $url,
        public string $dedupKey,
        public ?string $scoreSummary = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->student->name.' is halfway through attempts on '.$this->activityTitle)
            ->greeting('A learner needs attention')
            ->line($this->student->name.' has not passed "'.$this->activityTitle.'" and has used '.$this->attemptsUsed.' of '.$this->maxAttempts.' allowed attempts.');

        if (filled($this->scoreSummary)) {
            $mail->line('Latest score: '.$this->scoreSummary.'.');
        }

        return $mail
            ->action('View progress', $this->url)
            ->line('This is a one-time alert for this activity. The learner was not copied.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $body = $this->student->name.' has not passed "'.$this->activityTitle.'" ('.$this->attemptsUsed.'/'.$this->maxAttempts.' attempts).';

        if (filled($this->scoreSummary)) {
            $body .= ' Score: '.$this->scoreSummary.'.';
        }

        return [
            'title' => 'Halfway attempts: '.$this->student->name,
            'body' => $body,
            'url' => $this->url,
            'dedup_key' => $this->dedupKey,
            'student_id' => $this->student->id,
            'activity_type' => $this->activityType,
            'activity_id' => $this->activityId,
        ];
    }
}
