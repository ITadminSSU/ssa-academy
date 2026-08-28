<?php

namespace App\Services\UsExperience;

use App\Models\Course\Course;
use App\Models\Course\UsExperienceAttempt;
use App\Models\Course\UsExperiencePlan;
use App\Services\Payment\SubscriptionAccessService;
use App\Models\User;
use Illuminate\Support\Collection;

class UsExperienceUnlockService
{
    public function __construct(
        private SubscriptionAccessService $subscriptionAccess,
    ) {}

    public function studentOverview(Course $course, User $user): array
    {
        $ordered = $this->orderedReadyPlans($course);
        $planIds = $ordered->pluck('id');
        $attempts = $planIds->isEmpty()
            ? collect()
            : UsExperienceAttempt::query()
                ->whereIn('us_experience_plan_id', $planIds)
                ->where('user_id', $user->id)
                ->get()
                ->groupBy(fn (UsExperienceAttempt $attempt) => (int) $attempt->us_experience_plan_id);

        $mode = $this->subscriptionAccess->getAccessMode($user, $course);
        $canSeeScores = in_array($mode, ['full', 'completed_only'], true);
        $canUseFiles = $mode === 'full';

        return [
            'plans' => $this->studentPlanPayloads($ordered, $attempts, $canUseFiles, $canSeeScores),
            'can_use_files' => $canUseFiles,
            'can_see_scores' => $canSeeScores,
            'default_tolerance' => (float) config('us_experience.default_tolerance', 2),
            'pass_mark_hint' => (int) config('us_experience.default_pass_mark', 85),
        ];
    }

    /**
     * Published, ready plans in trainer sort order (one unlock line for the course).
     *
     * @return Collection<int, UsExperiencePlan>
     */
    public function orderedReadyPlans(Course $course): Collection
    {
        return UsExperiencePlan::query()
            ->where('course_id', $course->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (UsExperiencePlan $plan) => $plan->isReady())
            ->values();
    }

    /**
     * @param Collection<int, UsExperiencePlan> $orderedPlans
     * @param Collection<int, Collection<int, UsExperienceAttempt>> $attemptsByPlanId
     * @return array<int, array<string, mixed>>
     */
    public function studentPlanPayloads(
        Collection $orderedPlans,
        Collection $attemptsByPlanId,
        bool $canUseFiles,
        bool $canSeeScores,
    ): array {
        $previousPassed = true;
        $payloads = [];

        foreach ($orderedPlans as $plan) {
            $attempts = $attemptsByPlanId->get($plan->id)
                ?? $attemptsByPlanId->get((string) $plan->id)
                ?? collect();
            $attempts = Collection::wrap($attempts)->sortByDesc('attempt_number')->values();
            $passed = $attempts->contains(fn (UsExperienceAttempt $attempt) => $attempt->isPassed());
            $attemptsUsed = $attempts->count();
            $attemptsExhausted = !$passed && $attemptsUsed >= (int) $plan->max_attempts;
            $unlocked = $previousPassed;

            if (!$unlocked) {
                $status = 'locked';
            } elseif ($passed) {
                $status = 'passed';
            } elseif ($attemptsExhausted) {
                $status = 'failed';
            } else {
                $status = 'ongoing';
            }

            $bestAttempt = $attempts->sortByDesc('lines_percent')->first();
            $latestAttempt = $attempts->first();

            $payloads[] = [
                'id' => $plan->id,
                'title' => $plan->title,
                'group_name' => $plan->group_name,
                'group_description' => $plan->group_description,
                'sort_order' => $plan->sort_order,
                'pass_mark' => $plan->pass_mark,
                'max_attempts' => $plan->max_attempts,
                'attempts_used' => $attemptsUsed,
                'status' => $status,
                'unlocked' => $unlocked,
                'can_download' => $canUseFiles && $unlocked,
                'can_submit' => $canUseFiles && $unlocked && !$passed && !$attemptsExhausted,
                'accuracy' => $canSeeScores ? ($bestAttempt?->lines_percent) : null,
                'latest_attempt' => ($canSeeScores && $latestAttempt)
                    ? $latestAttempt->toStudentArray()
                    : null,
                'attempts' => $canSeeScores
                    ? $attempts->map(fn (UsExperienceAttempt $attempt) => $attempt->toStudentArray())->values()->all()
                    : [],
                'tutorial_video' => ($canSeeScores && $latestAttempt && $plan->tutorial_video_url)
                    ? [
                        'url' => $plan->tutorial_video_url,
                        'name' => $plan->tutorial_video_name ?: 'Walkthrough video',
                    ]
                    : null,
            ];

            $previousPassed = $passed;
        }

        return $payloads;
    }

    public function previousPlanIsPassed(UsExperiencePlan $plan, Collection $orderedPlans, User $user): bool
    {
        $index = $orderedPlans->search(fn (UsExperiencePlan $item) => (int) $item->id === (int) $plan->id);

        if ($index === false) {
            return false;
        }

        if ($index === 0) {
            return true;
        }

        $previous = $orderedPlans[$index - 1];

        return UsExperienceAttempt::query()
            ->where('us_experience_plan_id', $previous->id)
            ->where('user_id', $user->id)
            ->where('status', UsExperienceAttempt::STATUS_PASSED)
            ->exists();
    }

    public function assertCanDownload(UsExperiencePlan $plan, Collection $orderedPlans, User $user, bool $canUseFiles): void
    {
        if (!$canUseFiles) {
            abort(403, 'Resubscribe to download plan files.');
        }

        $this->assertUnlocked($plan, $orderedPlans, $user);
    }

    public function assertCanSubmit(UsExperiencePlan $plan, Collection $orderedPlans, User $user, bool $canUseFiles): void
    {
        if (!$canUseFiles) {
            abort(403, 'Resubscribe to submit plan work.');
        }

        $this->assertUnlocked($plan, $orderedPlans, $user);

        $attempts = UsExperienceAttempt::query()
            ->where('us_experience_plan_id', $plan->id)
            ->where('user_id', $user->id)
            ->get();

        if ($attempts->contains(fn (UsExperienceAttempt $attempt) => $attempt->isPassed())) {
            abort(422, 'This plan is already passed.');
        }

        if ($attempts->count() >= (int) $plan->max_attempts) {
            abort(422, 'No attempts remaining for this plan.');
        }
    }

    private function assertUnlocked(UsExperiencePlan $plan, Collection $orderedPlans, User $user): void
    {
        if (!$plan->isReady()) {
            abort(404);
        }

        if (!$this->previousPlanIsPassed($plan, $orderedPlans, $user)) {
            abort(403, 'Pass the previous plan before opening this one.');
        }
    }
}
