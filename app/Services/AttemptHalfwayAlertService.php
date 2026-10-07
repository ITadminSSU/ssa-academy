<?php

namespace App\Services;

use App\Models\Course\QuizSubmission;
use App\Models\Course\UsExperienceAttempt;
use App\Models\User;
use App\Notifications\AttemptHalfwayAlertNotification;
use App\Support\AttemptHalfwayThreshold;
use Illuminate\Support\Facades\Cache;
use Modules\Exam\Models\ExamAttempt;

class AttemptHalfwayAlertService
{
    public function afterQuizSubmission(QuizSubmission $submission): void
    {
        $submission->loadMissing(['user', 'section_quiz.course.instructor.user']);

        $quiz = $submission->section_quiz;

        if (! $quiz || ! $submission->user) {
            return;
        }

        $course = $quiz->course;
        $passMark = $quiz->pass_mark;
        $score = $submission->total_marks;

        $this->notifyIfNeeded(
            instructorUser: $course?->instructor?->user,
            student: $submission->user,
            activityType: 'quiz',
            activityId: (int) $quiz->id,
            activityTitle: (string) $quiz->title,
            maxAttempts: (int) $quiz->retake,
            attemptsUsed: (int) $submission->attempts,
            passed: (bool) $submission->is_passed,
            url: $course ? route('student-progress.show', ['course' => $course->id, 'search' => $submission->user->email]) : route('student-progress.index'),
            scoreSummary: $this->scoreSummary($score, $passMark),
        );
    }

    public function afterExamAttempt(ExamAttempt $attempt): void
    {
        $attempt->loadMissing(['user', 'exam.instructor.user']);

        if ($attempt->status !== 'completed' || ! $attempt->exam || ! $attempt->user) {
            return;
        }

        $exam = $attempt->exam;
        $attemptsUsed = ExamAttempt::query()
            ->where('user_id', $attempt->user_id)
            ->where('exam_id', $attempt->exam_id)
            ->whereIn('status', ['completed', 'submitted'])
            ->count();

        $this->notifyIfNeeded(
            instructorUser: $exam->instructor?->user,
            student: $attempt->user,
            activityType: 'exam',
            activityId: (int) $exam->id,
            activityTitle: (string) $exam->title,
            maxAttempts: (int) $exam->max_attempts,
            attemptsUsed: $attemptsUsed,
            passed: (bool) $attempt->is_passed,
            url: route('exams.edit', ['exam' => $exam->id, 'tab' => 'attempts']),
            scoreSummary: $this->scoreSummary($attempt->percentage, $exam->pass_mark, isPercent: true),
        );
    }

    public function afterUsExperienceAttempt(UsExperienceAttempt $attempt): void
    {
        $attempt->loadMissing(['user', 'plan.course.instructor.user']);

        if ($attempt->status !== UsExperienceAttempt::STATUS_FAILED || ! $attempt->plan || ! $attempt->user) {
            return;
        }

        $plan = $attempt->plan;
        $course = $plan->course;
        $attemptsUsed = UsExperienceAttempt::query()
            ->where('us_experience_plan_id', $plan->id)
            ->where('user_id', $attempt->user_id)
            ->count();

        $this->notifyIfNeeded(
            instructorUser: $course?->instructor?->user,
            student: $attempt->user,
            activityType: 'us_experience',
            activityId: (int) $plan->id,
            activityTitle: (string) $plan->title,
            maxAttempts: (int) $plan->max_attempts,
            attemptsUsed: $attemptsUsed,
            passed: false,
            url: $course ? route('student-progress.show', ['course' => $course->id, 'search' => $attempt->user->email]) : route('student-progress.index'),
            scoreSummary: $this->scoreSummary($attempt->lines_percent, $plan->pass_mark, isPercent: true),
        );
    }

    private function notifyIfNeeded(
        ?User $instructorUser,
        User $student,
        string $activityType,
        int $activityId,
        string $activityTitle,
        int $maxAttempts,
        int $attemptsUsed,
        bool $passed,
        string $url,
        ?string $scoreSummary,
    ): void {
        if (! AttemptHalfwayThreshold::shouldNotify($maxAttempts, $attemptsUsed, $passed)) {
            return;
        }

        if (! $instructorUser || ! $instructorUser->isAccountActive()) {
            return;
        }

        $dedupKey = $this->dedupKey($student->id, $activityType, $activityId);

        if ($this->alreadyNotified($instructorUser, $dedupKey)) {
            return;
        }

        $cacheKey = 'attempt-halfway-alert:'.$dedupKey;

        if (! Cache::add($cacheKey, 1, now()->addDays(60))) {
            return;
        }

        $instructorUser->notify(new AttemptHalfwayAlertNotification(
            student: $student,
            activityType: $activityType,
            activityId: $activityId,
            activityTitle: $activityTitle,
            attemptsUsed: $attemptsUsed,
            maxAttempts: $maxAttempts,
            url: $url,
            dedupKey: $dedupKey,
            scoreSummary: $scoreSummary,
        ));
    }

    public function dedupKey(int $studentId, string $activityType, int $activityId): string
    {
        return "student:{$studentId}:{$activityType}:{$activityId}";
    }

    private function alreadyNotified(User $instructor, string $dedupKey): bool
    {
        return $instructor->notifications()
            ->where('type', AttemptHalfwayAlertNotification::class)
            ->where('data->dedup_key', $dedupKey)
            ->exists();
    }

    private function scoreSummary(mixed $score, mixed $passMark, bool $isPercent = false): ?string
    {
        if ($score === null || $passMark === null || $score === '' || $passMark === '') {
            return null;
        }

        $suffix = $isPercent ? '%' : '';

        return $this->formatNumber($score).$suffix.' vs pass mark '.$this->formatNumber($passMark).$suffix;
    }

    private function formatNumber(mixed $value): string
    {
        $number = (float) $value;

        if (floor($number) == $number) {
            return (string) (int) $number;
        }

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }
}
