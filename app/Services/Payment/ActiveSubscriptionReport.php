<?php

namespace App\Services\Payment;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Builds the active-subscription report from a Stripe read. It does not write
 * enrollments, payments, or subscription rows.
 */
class ActiveSubscriptionReport
{
    public function __construct(private StripeActiveSubscriptionLister $stripe) {}

    /**
     * @var list<array<string, mixed>>|null
     */
    private ?array $matched = null;

    /**
     * @var list<array{label: string, course_id: int|null, course: string}>|null
     */
    private ?array $classified = null;

    /**
     * @param  array{search?: string, course?: string, renewal?: string}  $filters
     */
    public function paginate(array $filters, int $page, int $perPage): LengthAwarePaginator
    {
        $rows = $this->filtered($filters);
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $slice = array_slice($rows, ($page - 1) * $perPage, $perPage);

        return new LengthAwarePaginator(
            array_values($slice),
            count($rows),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }

    /**
     * @param  array{search?: string, course?: string, renewal?: string}  $filters
     * @return list<array{label: string, count: int}>
     */
    public function statusSummary(array $filters): array
    {
        $course = trim((string) ($filters['course'] ?? ''));
        $counts = [
            'Active' => 0,
            'Inactive' => 0,
            'Canceled' => 0,
        ];

        foreach ($this->classified() as $row) {
            if ($course !== '' && (string) ($row['course_id'] ?? '') !== $course) {
                continue;
            }

            $counts[$row['label']]++;
        }

        $summary = [];

        foreach ($counts as $label => $count) {
            $summary[] = [
                'label' => $label,
                'count' => $count,
            ];
        }

        return $summary;
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    public function courseOptions(): array
    {
        $courses = [];

        foreach ($this->classified() as $row) {
            $id = (int) ($row['course_id'] ?? 0);
            $title = (string) $row['course'];

            if ($id < 1 || $title === '' || isset($courses[$id])) {
                continue;
            }

            $courses[$id] = [
                'id' => $id,
                'title' => $title,
            ];
        }

        $options = array_values($courses);
        usort($options, fn (array $left, array $right) => strcasecmp($left['title'], $right['title']));

        return $options;
    }

    /**
     * @param  array{search?: string, course?: string, renewal?: string}  $filters
     */
    public function toCsv(array $filters): string
    {
        $handle = fopen('php://temp', 'w+');
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($handle, [
            'Student name',
            'Email',
            'Course',
            'Stripe status',
            'Current period ends',
            'Cancels at period end',
        ]);

        foreach ($this->filtered($filters) as $row) {
            fputcsv($handle, [
                $row['student_name'],
                $row['email'],
                $row['course'],
                $row['stripe_status_label'],
                $row['period_end'],
                $row['cancel_label'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /**
     * @param  array{search?: string, course?: string, renewal?: string}  $filters
     * @return list<array<string, mixed>>
     */
    public function filtered(array $filters): array
    {
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $course = trim((string) ($filters['course'] ?? ''));
        $renewal = trim((string) ($filters['renewal'] ?? ''));

        return array_values(array_filter($this->matched(), function (array $row) use ($search, $course, $renewal) {
            if ($search !== '') {
                $haystack = mb_strtolower($row['student_name'].' '.$row['email']);

                if (! str_contains($haystack, $search)) {
                    return false;
                }
            }

            if ($course !== '' && (string) ($row['course_id'] ?? '') !== $course) {
                return false;
            }

            if ($renewal === 'renews' && $row['cancel_at_period_end']) {
                return false;
            }

            if ($renewal === 'cancels' && ! $row['cancel_at_period_end']) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function matched(): array
    {
        if ($this->matched !== null) {
            return $this->matched;
        }

        $stripeRows = array_values(array_filter(
            $this->stripe->list(),
            fn (array $row) => ($row['status'] ?? '') === 'active',
        ));

        $matched = [];

        foreach ($this->attachCourse($stripeRows) as $row) {
            $user = $row['user'];
            $periodEnd = $row['current_period_end']
                ? Carbon::createFromTimestampUTC((int) $row['current_period_end'])->timezone(config('app.timezone'))
                : null;

            $matched[] = [
                'student_name' => trim((string) ($user->name ?? '')) !== ''
                    ? (string) $user->name
                    : (string) ($row['customer_name'] ?? ''),
                'email' => trim((string) ($user->email ?? '')) !== ''
                    ? (string) $user->email
                    : (string) ($row['customer_email'] ?? ''),
                'course' => (string) $row['course'],
                'course_id' => $row['course_id'],
                'stripe_status' => 'active',
                'stripe_status_label' => 'Active',
                'period_end' => $periodEnd ? $periodEnd->format('F d, Y') : '',
                'cancel_at_period_end' => (bool) $row['cancel_at_period_end'],
                'cancel_label' => $row['cancel_at_period_end'] ? 'Yes' : 'No',
            ];
        }

        return $this->matched = $matched;
    }

    /**
     * @return list<array{label: string, course_id: int|null, course: string}>
     */
    private function classified(): array
    {
        if ($this->classified !== null) {
            return $this->classified;
        }

        $rows = [];

        foreach ($this->matched() as $row) {
            $rows[] = [
                'label' => 'Active',
                'course_id' => $row['course_id'],
                'course' => (string) $row['course'],
            ];
        }

        $extra = $this->stripe->listStatuses(StripeActiveSubscriptionLister::SUMMARY_STATUSES);

        foreach ($this->attachCourse($extra) as $row) {
            $label = $this->summaryLabel((string) ($row['status'] ?? ''));

            if ($label === null) {
                continue;
            }

            $rows[] = [
                'label' => $label,
                'course_id' => $row['course_id'],
                'course' => (string) $row['course'],
            ];
        }

        return $this->classified = $rows;
    }

    private function summaryLabel(string $status): ?string
    {
        return match ($status) {
            'canceled' => 'Canceled',
            'trialing', 'past_due', 'unpaid', 'paused', 'incomplete', 'incomplete_expired' => 'Inactive',
            default => null,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $stripeRows
     * @return list<array<string, mixed>>
     */
    private function attachCourse(array $stripeRows): array
    {
        $subscriptionIds = array_values(array_filter(array_map(
            fn (array $row) => (string) ($row['stripe_subscription_id'] ?? ''),
            $stripeRows,
        )));

        $localByStripeId = $subscriptionIds === []
            ? collect()
            : DB::table('subscriptions')
                ->whereIn('stripe_subscription_id', $subscriptionIds)
                ->get(['stripe_subscription_id', 'user_id', 'course_id'])
                ->keyBy('stripe_subscription_id');

        $userIds = [];
        $courseIds = [];
        $resolved = [];

        foreach ($stripeRows as $row) {
            $local = $localByStripeId->get($row['stripe_subscription_id']);
            $userId = $row['user_id'] ?: (int) ($local->user_id ?? 0);
            $courseId = $row['course_id'] ?: (int) ($local->course_id ?? 0);
            $resolved[] = [
                'row' => $row,
                'user_id' => $userId,
                'course_id' => $courseId,
            ];

            if ($userId > 0) {
                $userIds[] = $userId;
            }

            if ($courseId > 0) {
                $courseIds[] = $courseId;
            }
        }

        $users = $userIds === []
            ? collect()
            : DB::table('users')->whereIn('id', array_unique($userIds))->get(['id', 'name', 'email'])->keyBy('id');
        $courses = $courseIds === []
            ? collect()
            : DB::table('courses')->whereIn('id', array_unique($courseIds))->get(['id', 'title'])->keyBy('id');

        $attached = [];

        foreach ($resolved as $item) {
            $row = $item['row'];
            $userId = $item['user_id'];
            $courseId = $item['course_id'];
            $course = $courseId > 0 ? $courses->get($courseId) : null;

            $attached[] = array_merge($row, [
                'user' => $userId > 0 ? $users->get($userId) : null,
                'course' => (string) ($course->title ?? ''),
                'course_id' => $courseId > 0 ? $courseId : null,
            ]);
        }

        return $attached;
    }
}
