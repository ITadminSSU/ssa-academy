<?php

namespace App\Services\Payment;

use App\Enums\EnrollmentAccessStatus;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class CourseAccessMoveService
{
    public function __construct(private StripeSubscriptionCourseAssigner $stripeCourse) {}

    /**
     * @return list<array{id: int, title: string, previous: bool}>
     */
    public function destinationOptions(CourseEnrollment $enrollment): array
    {
        $previousIds = CourseEnrollment::query()
            ->where('user_id', $enrollment->user_id)
            ->where('access_status', EnrollmentAccessStatus::TRANSFERRED->value)
            ->where('course_id', '!=', $enrollment->course_id)
            ->pluck('course_id');

        return Course::query()
            ->select(['id', 'title', 'status'])
            ->where('id', '!=', $enrollment->course_id)
            ->where(function ($query) use ($previousIds) {
                $query->where('status', 'approved');

                if ($previousIds->isNotEmpty()) {
                    $query->orWhereIn('id', $previousIds);
                }
            })
            ->orderBy('title')
            ->get()
            ->map(fn (Course $course) => [
                'id' => (int) $course->id,
                'title' => (string) $course->title,
                'previous' => $previousIds->contains(fn ($id) => (int) $id === (int) $course->id),
            ])
            ->values()
            ->all();
    }

    public function move(CourseEnrollment $enrollment, int $destinationCourseId): CourseEnrollment
    {
        $enrollment->loadMissing('subscription');

        if ($enrollment->isTransferred()) {
            throw new \InvalidArgumentException('Move the course this student is in now. A locked course opens again when they are moved back to it.');
        }

        if ($destinationCourseId === (int) $enrollment->course_id) {
            throw new \InvalidArgumentException('Choose a different course.');
        }

        $destination = Course::query()->find($destinationCourseId);

        if (! $destination) {
            throw new \InvalidArgumentException('Choose a course.');
        }

        $existing = CourseEnrollment::query()
            ->where('user_id', $enrollment->user_id)
            ->where('course_id', $destination->id)
            ->orderByDesc('id')
            ->get();

        $blocking = $existing->first(fn (CourseEnrollment $row) => ! $row->isTransferred());

        if ($blocking) {
            throw new \InvalidArgumentException('This student already has access to that course.');
        }

        $reopen = $existing->first(fn (CourseEnrollment $row) => $row->isTransferred());
        $subscription = $enrollment->subscription;

        if (filled($subscription?->stripe_subscription_id)) {
            try {
                $this->stripeCourse->assignCourse($subscription, (int) $destination->id);
            } catch (\Throwable $exception) {
                throw new \RuntimeException(
                    $exception->getMessage() !== ''
                        ? $exception->getMessage()
                        : 'Stripe could not update this subscription. Nothing was changed.',
                    0,
                    $exception,
                );
            }
        }

        return DB::transaction(function () use ($enrollment, $destination, $reopen, $subscription) {
            if ($subscription) {
                $subscription->update(['course_id' => $destination->id]);
            }

            $status = $this->destinationStatus($enrollment, $subscription);
            $attributes = [
                'enrollment_type' => $enrollment->enrollment_type,
                'access_status' => $status,
                'subscription_id' => $enrollment->subscription_id,
                'suspended_at' => $status === EnrollmentAccessStatus::SUSPENDED ? now() : null,
                'expiry_date' => $enrollment->expiry_date,
                ...$this->seatAttributes($enrollment),
            ];

            if ($reopen) {
                $reopen->update($attributes);
                $opened = $reopen->fresh();
            } else {
                $opened = CourseEnrollment::query()->create([
                    ...$attributes,
                    'user_id' => $enrollment->user_id,
                    'course_id' => $destination->id,
                    'entry_date' => now(),
                ]);
            }

            $enrollment->update([
                'access_status' => EnrollmentAccessStatus::TRANSFERRED,
                'subscription_id' => null,
                'suspended_at' => null,
                ...$this->clearedSeatAttributes(),
            ]);

            return $opened;
        });
    }

    private function destinationStatus(CourseEnrollment $enrollment, ?Subscription $subscription): EnrollmentAccessStatus
    {
        if ($enrollment->isReservedSeat()) {
            return EnrollmentAccessStatus::RESERVED;
        }

        if ($subscription?->accessRetained() || $subscription?->grantsFullAccess()) {
            return EnrollmentAccessStatus::ACTIVE;
        }

        if ($subscription) {
            return EnrollmentAccessStatus::SUSPENDED;
        }

        return $enrollment->access_status === EnrollmentAccessStatus::SUSPENDED
            ? EnrollmentAccessStatus::SUSPENDED
            : EnrollmentAccessStatus::ACTIVE;
    }

    /**
     * @return array<string, mixed>
     */
    private function seatAttributes(CourseEnrollment $enrollment): array
    {
        return [
            'pef_granted' => (bool) $enrollment->pef_granted,
            'deposit_amount' => $enrollment->deposit_amount,
            'deposit_paid_at' => $enrollment->deposit_paid_at,
            'deposit_payment_history_id' => $enrollment->deposit_payment_history_id,
            'balance_amount' => $enrollment->balance_amount,
            'balance_due_at' => $enrollment->balance_due_at,
            'balance_deadline_at' => $enrollment->balance_deadline_at,
            'balance_paid_at' => $enrollment->balance_paid_at,
            'balance_payment_history_id' => $enrollment->balance_payment_history_id,
            'forfeited_at' => $enrollment->forfeited_at,
            'launch_offer_cohort' => $enrollment->launch_offer_cohort,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clearedSeatAttributes(): array
    {
        return [
            'pef_granted' => false,
            'deposit_amount' => null,
            'deposit_paid_at' => null,
            'deposit_payment_history_id' => null,
            'balance_amount' => null,
            'balance_due_at' => null,
            'balance_deadline_at' => null,
            'balance_paid_at' => null,
            'balance_payment_history_id' => null,
            'forfeited_at' => null,
            'launch_offer_cohort' => null,
        ];
    }
}
