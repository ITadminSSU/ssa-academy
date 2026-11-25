<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;

class UsExperienceFastResubmitNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $student,
        public string $planTitle,
        public int $planId,
        public int $attemptId,
        public int $attemptNumber,
        public string $gapAfterAttempt,
        public string $scoreSummary,
        public string $url,
        public string $dedupKey,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($this->alreadyDelivered($notifiable)) {
            return [];
        }

        if (! Cache::add($this->deliveredKey(), 1, now()->addDays(60))) {
            return [];
        }

        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Review a quick resubmit from '.$this->student->name)
            ->greeting('A resubmit needs review')
            ->line($this->student->name.' resubmitted "'.$this->planTitle.'" '.$this->gapAfterAttempt.'.')
            ->line('Score: '.$this->scoreSummary.'.')
            ->action('Review attempt', $this->url)
            ->line('This needs a review because the student resubmitted quickly. The learner was not copied.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Review resubmit: '.$this->student->name,
            'body' => $this->student->name.' resubmitted "'.$this->planTitle.'" '.$this->gapAfterAttempt.'. Score: '.$this->scoreSummary.'.',
            'url' => $this->url,
            'dedup_key' => $this->dedupKey,
            'student_id' => $this->student->id,
            'activity_type' => 'us_experience',
            'activity_id' => $this->planId,
            'attempt_id' => $this->attemptId,
            'attempt_number' => $this->attemptNumber,
        ];
    }

    public function deliveredKey(): string
    {
        return 'us-experience-fast-resubmit:delivered:'.$this->dedupKey;
    }

    private function alreadyDelivered(object $notifiable): bool
    {
        if (! $notifiable instanceof User) {
            return false;
        }

        return $notifiable->notifications()
            ->where('type', self::class)
            ->where('data->dedup_key', $this->dedupKey)
            ->exists();
    }
}
