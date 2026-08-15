<?php

namespace App\Services\Payment;

use App\Enums\EnrollmentAccessStatus;
use App\Models\Course\CourseEnrollment;
use Carbon\Carbon;

class LaunchOfferReminderService
{
    public function __construct(
        private LaunchOfferMailService $mail,
    ) {}

    /**
     * @return array{launch: int, mid: int, final: int}
     */
    public function sendDueReminders(?Carbon $now = null): array
    {
        $now ??= now();
        $stats = ['launch' => 0, 'mid' => 0, 'final' => 0];

        CourseEnrollment::query()
            ->with(['user', 'course'])
            ->where('access_status', EnrollmentAccessStatus::RESERVED->value)
            ->whereNull('balance_paid_at')
            ->whereNull('forfeited_at')
            ->whereNotNull('balance_due_at')
            ->whereNotNull('balance_deadline_at')
            ->where('balance_deadline_at', '>=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($enrollments) use ($now, &$stats) {
                foreach ($enrollments as $enrollment) {
                    if (! $enrollment->isReservedSeat()) {
                        continue;
                    }

                    $dueAt = $enrollment->balance_due_at;
                    $deadlineAt = $enrollment->balance_deadline_at;
                    if (! $dueAt || ! $deadlineAt) {
                        continue;
                    }

                    if ($now->lessThan($dueAt)) {
                        continue;
                    }

                    if (
                        empty($enrollment->balance_due_notice_sent_at)
                        && $this->mail->sendBalanceDueNotice($enrollment)
                    ) {
                        $stats['launch']++;
                        $enrollment->refresh();
                    }

                    $midAt = $this->midReminderAt($dueAt, $deadlineAt);
                    if (
                        empty($enrollment->balance_mid_reminder_sent_at)
                        && $now->greaterThanOrEqualTo($midAt)
                        && $this->mail->sendBalanceMidReminder($enrollment)
                    ) {
                        $stats['mid']++;
                        $enrollment->refresh();
                    }

                    $finalDayStart = $deadlineAt->copy()->startOfDay();
                    if (
                        empty($enrollment->balance_final_reminder_sent_at)
                        && $now->greaterThanOrEqualTo($finalDayStart)
                        && $this->mail->sendBalanceFinalReminder($enrollment)
                    ) {
                        $stats['final']++;
                    }
                }
            });

        return $stats;
    }

    private function midReminderAt(Carbon $dueAt, Carbon $deadlineAt): Carbon
    {
        $graceDays = max(0, (int) $dueAt->diffInDays($deadlineAt->copy()->startOfDay()));
        $offset = (int) floor($graceDays / 2);

        if ($offset < 1) {
            return $dueAt->copy()->addDay()->startOfDay();
        }

        return $dueAt->copy()->addDays($offset)->startOfDay();
    }
}
