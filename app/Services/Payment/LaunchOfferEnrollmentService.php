<?php

namespace App\Services\Payment;

use App\Enums\EnrollmentAccessStatus;
use App\Enums\PaymentBillingType;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\User;
use App\Services\Course\CourseEnrollmentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\PaymentGateways\Models\PaymentHistory;
use Modules\PaymentGateways\Services\PaymentService;

class LaunchOfferEnrollmentService
{
    public function __construct(
        private LaunchOfferService $launchOffer,
        private CourseEnrollmentService $courseEnrollment,
        private PaymentService $paymentService,
    ) {}

    /**
     * @return 'deposit'|'balance'|'full_launch'|'upfront_subscription'|'legacy_subscription'|'legacy_one_time'|null
     */
    public function resolveCheckoutMode(User $user, Course $course): ?string
    {
        if ($course->pricing_type !== 'paid') {
            return null;
        }

        $enrollment = CourseEnrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if ($enrollment?->hasFullAccess()) {
            return null;
        }

        if ($enrollment?->isReservedSeat()) {
            if ($this->launchOffer->toFrontendPayload($course, $enrollment)['can_pay_balance'] ?? false) {
                return 'balance';
            }

            return null;
        }

        if ($this->launchOffer->allowsDepositCheckout($course)) {
            return 'deposit';
        }

        if ($this->launchOffer->allowsFullLaunchCheckout($course)) {
            return 'full_launch';
        }

        if ($course->usesUpfrontSubscription()) {
            return 'upfront_subscription';
        }

        if ($course->usesMonthlyOnlySubscription()) {
            return 'legacy_subscription';
        }

        return 'legacy_one_time';
    }

    public function recordDepositPayment(
        User $user,
        Course $course,
        string $paymentMethod,
        string $transactionId,
        float $amount,
    ): CourseEnrollment {
        return DB::transaction(function () use ($user, $course, $paymentMethod, $transactionId, $amount) {
            if (PaymentHistory::where('transaction_id', $transactionId)->exists()) {
                return CourseEnrollment::query()
                    ->where('user_id', $user->id)
                    ->where('course_id', $course->id)
                    ->firstOrFail();
            }

            $deposit = $this->launchOffer->depositAmount($course);
            $balance = $this->launchOffer->balanceAmount($course);

            $history = $this->paymentService->recordLaunchOfferPayment(
                user: $user,
                course: $course,
                paymentMethod: $paymentMethod,
                transactionId: $transactionId,
                amount: $amount > 0 ? $amount : $deposit,
                billingType: PaymentBillingType::DEPOSIT,
            );

            $enrollment = CourseEnrollment::query()
                ->where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->first();

            $payload = [
                'user_id' => $user->id,
                'course_id' => $course->id,
                'enrollment_type' => 'deposit',
                'access_status' => EnrollmentAccessStatus::RESERVED->value,
                'deposit_amount' => $deposit,
                'deposit_paid_at' => now(),
                'deposit_payment_history_id' => $history->id,
                'balance_amount' => $balance,
                'balance_due_at' => $this->launchOffer->balanceDueAt($course),
                'balance_deadline_at' => $this->launchOffer->balanceDeadlineAt($course),
                'launch_offer_cohort' => 'pre_register',
            ];

            if ($enrollment) {
                $enrollment->update($payload);

                return $enrollment->fresh();
            }

            return $this->courseEnrollment->createCourseEnroll($payload, allowBeforeLaunch: true);
        });
    }

    public function recordBalancePayment(
        User $user,
        Course $course,
        string $paymentMethod,
        string $transactionId,
        float $amount,
    ): CourseEnrollment {
        return DB::transaction(function () use ($user, $course, $paymentMethod, $transactionId, $amount) {
            $enrollment = CourseEnrollment::query()
                ->where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->firstOrFail();

            if (! $enrollment->isReservedSeat()) {
                throw new \RuntimeException('No reserved seat found for balance payment.');
            }

            if (PaymentHistory::where('transaction_id', $transactionId)->exists()) {
                return $enrollment->fresh();
            }

            $balance = (float) ($enrollment->balance_amount ?? $this->launchOffer->balanceAmount($course));

            $history = $this->paymentService->recordLaunchOfferPayment(
                user: $user,
                course: $course,
                paymentMethod: $paymentMethod,
                transactionId: $transactionId,
                amount: $amount > 0 ? $amount : $balance,
                billingType: PaymentBillingType::BALANCE,
            );

            $enrollment->update([
                'balance_paid_at' => now(),
                'balance_payment_history_id' => $history->id,
                'access_status' => EnrollmentAccessStatus::ACTIVE,
                'enrollment_type' => 'paid',
            ]);

            return $enrollment->fresh();
        });
    }

    public function forfeitExpiredReservations(?Carbon $now = null): int
    {
        $now ??= now();
        $count = 0;

        CourseEnrollment::query()
            ->where('access_status', EnrollmentAccessStatus::RESERVED->value)
            ->whereNull('balance_paid_at')
            ->whereNull('forfeited_at')
            ->whereNotNull('balance_deadline_at')
            ->where('balance_deadline_at', '<', $now)
            ->orderBy('id')
            ->chunkById(100, function ($enrollments) use (&$count) {
                foreach ($enrollments as $enrollment) {
                    $enrollment->update([
                        'access_status' => EnrollmentAccessStatus::CANCELED,
                        'forfeited_at' => now(),
                    ]);
                    $count++;
                }
            });

        return $count;
    }
}
