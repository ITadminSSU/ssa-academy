<?php

namespace Modules\PaymentGateways\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payment\ActiveSubscriptionReport;
use App\Services\Payment\StripeActiveSubscriptionException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;

class ActiveSubscriptionReportController extends Controller
{
    public function __construct(private ActiveSubscriptionReport $report) {}

    public function index(Request $request)
    {
        $filters = $this->filters($request);

        try {
            $subscriptions = $this->report->paginate($filters, (int) $request->input('page', 1), $this->perPage($request));
            $courses = $this->report->courseOptions();
            $error = null;
        } catch (StripeActiveSubscriptionException $exception) {
            $subscriptions = $this->emptyPage($request);
            $courses = [];
            $error = $exception->getMessage();
        }

        return Inertia::render('dashboard/payment-reports/active-subscriptions', [
            'subscriptions' => $subscriptions,
            'courses' => $courses,
            'filters' => $filters,
            'error' => $error,
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->filters($request);

        try {
            $csv = $this->report->toCsv($filters);
        } catch (StripeActiveSubscriptionException $exception) {
            return redirect()
                ->route('payment-reports.active-subscriptions.index', array_filter($filters))
                ->with('error', $exception->getMessage());
        }

        $filename = 'active-subscriptions-'.now()->format('Y-m-d').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return array{search: string, course: string, renewal: string}
     */
    private function filters(Request $request): array
    {
        $renewal = (string) $request->input('renewal', '');

        if (! in_array($renewal, ['renews', 'cancels'], true)) {
            $renewal = '';
        }

        $course = (string) $request->input('course', '');

        if ($course !== '' && ! ctype_digit($course)) {
            $course = '';
        }

        return [
            'search' => trim((string) $request->input('search', '')),
            'course' => $course,
            'renewal' => $renewal,
        ];
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 10);

        return in_array($perPage, [10, 15, 20, 25, 50], true) ? $perPage : 10;
    }

    private function emptyPage(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $this->perPage($request), 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }
}
