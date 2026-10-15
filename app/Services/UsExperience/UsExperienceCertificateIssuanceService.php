<?php

namespace App\Services\UsExperience;

use App\Models\Course\Course;
use App\Models\Course\UsExperienceAttempt;
use App\Models\Course\UsExperienceCertificate;
use App\Models\Course\UsExperiencePlan;
use App\Models\User;
use App\Support\ReferenceNumberService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class UsExperienceCertificateIssuanceService
{
    public function __construct(
        private ReferenceNumberService $referenceNumbers,
        private UsExperienceUnlockService $unlock,
    ) {}

    /**
     * Issue one certificate for this level when every ready plan in the group
     * has a passing attempt. Returns the existing certificate if it was already issued.
     */
    public function issueIfGroupPassed(User $user, Course $course, string $groupName): ?UsExperienceCertificate
    {
        if (! Schema::hasTable('us_experience_certificates') || trim($groupName) === '') {
            return null;
        }

        $readyPlans = $this->readyPlansInGroup($course, $groupName);

        if ($readyPlans->isEmpty() || ! $this->everyPlanPassed($user, $readyPlans)) {
            return null;
        }

        $existing = $this->find($user, $course, $groupName);

        if ($existing) {
            return $existing;
        }

        $verificationReference = $this->referenceNumbers->generateCertificateVerificationReference();

        try {
            return UsExperienceCertificate::query()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'group_name' => $groupName,
                'identifier' => $verificationReference,
                'certificate_id' => $this->referenceNumbers->generateCertificateId(),
                'verification_reference' => $verificationReference,
            ]);
        } catch (QueryException) {
            return $this->find($user, $course, $groupName);
        }
    }

    /**
     * @return list<array{group_name: string, certificate_id: ?string, verification_reference: ?string, issued_at: ?string}>
     */
    public function forStudent(Course $course, User $user): array
    {
        if (! Schema::hasTable('us_experience_certificates')) {
            return [];
        }

        $groups = $this->unlock->orderedReadyPlans($course)
            ->map(fn (UsExperiencePlan $plan) => (string) $plan->group_name)
            ->filter(fn (string $groupName) => trim($groupName) !== '')
            ->unique()
            ->values();

        foreach ($groups as $groupName) {
            $this->issueIfGroupPassed($user, $course, $groupName);
        }

        return UsExperienceCertificate::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->orderBy('id')
            ->get()
            ->map(fn (UsExperienceCertificate $certificate) => [
                'group_name' => $certificate->group_name,
                'certificate_id' => $certificate->certificate_id,
                'verification_reference' => $certificate->verification_reference,
                'issued_at' => $certificate->created_at?->format('F j, Y'),
            ])
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, UsExperiencePlan>
     */
    private function readyPlansInGroup(Course $course, string $groupName)
    {
        return $this->unlock->orderedReadyPlans($course)
            ->filter(fn (UsExperiencePlan $plan) => (string) $plan->group_name === $groupName)
            ->values();
    }

    /**
     * @param \Illuminate\Support\Collection<int, UsExperiencePlan> $readyPlans
     */
    private function everyPlanPassed(User $user, $readyPlans): bool
    {
        $passedPlanIds = UsExperienceAttempt::query()
            ->where('user_id', $user->id)
            ->whereIn('us_experience_plan_id', $readyPlans->pluck('id'))
            ->where('status', UsExperienceAttempt::STATUS_PASSED)
            ->pluck('us_experience_plan_id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        return $readyPlans->every(
            fn (UsExperiencePlan $plan) => $passedPlanIds->contains((int) $plan->id)
        );
    }

    private function find(User $user, Course $course, string $groupName): ?UsExperienceCertificate
    {
        return UsExperienceCertificate::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('group_name', $groupName)
            ->first();
    }
}
