<?php

namespace App\Http\Controllers;

use App\Enums\ScamTiplineStatus;
use App\Models\ScamTiplineReport;
use App\Services\ScamTiplineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScamTiplineController extends Controller
{
    public function __construct(private ScamTiplineService $tipline) {}

    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $q = $request->string('q')->trim()->toString();
        $showArchived = $request->boolean('archived');

        $query = ScamTiplineReport::query()
            ->with(['reviewer:id,name', 'audits' => fn ($a) => $a->with('user:id,name')->limit(8)])
            ->when($showArchived, fn ($q) => $q->onlyTrashed())
            ->when($status !== '' && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('reporter_name', 'like', $like)
                        ->orWhere('reporter_email', 'like', $like)
                        ->orWhere('link', 'like', $like)
                        ->orWhere('normalized_link', 'like', $like)
                        ->orWhere('details', 'like', $like)
                        ->orWhere('public_note', 'like', $like);
                });
            })
            ->latest('id');

        $reports = $query->paginate(20)->withQueryString()->through(function (ScamTiplineReport $report) {
            return [
                'id' => $report->id,
                'reporter_name' => $report->reporter_name,
                'reporter_email' => $report->reporter_email,
                'link' => $report->link,
                'normalized_link' => $report->normalized_link,
                'details' => $report->details,
                'screenshot' => $report->screenshot,
                'screenshot_name' => $report->screenshot_name,
                'status' => $report->status?->value,
                'status_label' => $report->status?->label(),
                'public_note' => $report->public_note,
                'is_published' => $report->is_published,
                'confirmed_at' => optional($report->confirmed_at)?->toIso8601String(),
                'duplicate_of_id' => $report->duplicate_of_id,
                'reviewed_by_name' => $report->reviewer?->name,
                'reviewed_at' => optional($report->reviewed_at)?->toIso8601String(),
                'created_at' => optional($report->created_at)?->toIso8601String(),
                'deleted_at' => optional($report->deleted_at)?->toIso8601String(),
                'share_url' => $report->share_url,
                'possible_duplicate' => (bool) $report->duplicate_of_id,
                'audits' => $report->audits->map(fn ($audit) => [
                    'id' => $audit->id,
                    'action' => $audit->action,
                    'from_status' => $audit->from_status,
                    'to_status' => $audit->to_status,
                    'user_name' => $audit->user?->name,
                    'created_at' => optional($audit->created_at)?->toIso8601String(),
                    'meta' => $audit->meta,
                ]),
            ];
        });

        $counts = [
            'all' => ScamTiplineReport::query()->count(),
            'new' => ScamTiplineReport::query()->where('status', ScamTiplineStatus::New)->count(),
            'investigating' => ScamTiplineReport::query()->where('status', ScamTiplineStatus::Investigating)->count(),
            'confirmed' => ScamTiplineReport::query()->where('status', ScamTiplineStatus::Confirmed)->count(),
            'dismissed' => ScamTiplineReport::query()->where('status', ScamTiplineStatus::Dismissed)->count(),
            'duplicate' => ScamTiplineReport::query()->where('status', ScamTiplineStatus::Duplicate)->count(),
            'archived' => ScamTiplineReport::onlyTrashed()->count(),
        ];

        return Inertia::render('dashboard/scam-tipline/index', [
            'reports' => $reports,
            'counts' => $counts,
            'filters' => [
                'status' => $status !== '' ? $status : 'all',
                'q' => $q,
                'archived' => $showArchived,
            ],
            'statuses' => collect(ScamTiplineStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function update(Request $request, ScamTiplineReport $report)
    {
        if ($request->input('duplicate_of_id') === '' || $request->input('duplicate_of_id') === null) {
            $request->merge(['duplicate_of_id' => null]);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(ScamTiplineStatus::values())],
            'public_note' => ['nullable', 'string', 'max:500'],
            'is_published' => ['sometimes', 'boolean'],
            'duplicate_of_id' => ['nullable', 'integer', 'exists:scam_tipline_reports,id'],
        ]);

        $this->tipline->updateReport($report, $data, $request->user());

        return redirect()->back()->with('success', 'Report updated.');
    }

    public function destroy(Request $request, ScamTiplineReport $report)
    {
        $this->tipline->softDelete($report, $request->user());

        return redirect()->back()->with('success', 'Report archived.');
    }

    public function restore(int $report)
    {
        $model = ScamTiplineReport::onlyTrashed()->findOrFail($report);
        $model->restore();

        $this->tipline->audit(
            $model,
            request()->user(),
            'restored',
            $model->status?->value,
            $model->status?->value,
            []
        );

        return redirect()->back()->with('success', 'Report restored.');
    }

    public function export(Request $request): StreamedResponse
    {
        $status = $request->string('status')->toString();
        $q = $request->string('q')->trim()->toString();

        $filename = 'fraud-training-tipline-'.now()->format('Y-m-d-His').'.csv';

        return Response::streamDownload(function () use ($status, $q) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'id',
                'status',
                'reporter_name',
                'reporter_email',
                'link',
                'normalized_link',
                'details',
                'public_note',
                'is_published',
                'confirmed_at',
                'duplicate_of_id',
                'created_at',
            ]);

            ScamTiplineReport::query()
                ->when($status !== '' && $status !== 'all', fn ($query) => $query->where('status', $status))
                ->when($q !== '', function ($query) use ($q) {
                    $like = '%'.$q.'%';
                    $query->where(function ($inner) use ($like) {
                        $inner->where('reporter_name', 'like', $like)
                            ->orWhere('reporter_email', 'like', $like)
                            ->orWhere('link', 'like', $like)
                            ->orWhere('details', 'like', $like);
                    });
                })
                ->orderBy('id')
                ->chunk(200, function ($rows) use ($handle) {
                    foreach ($rows as $report) {
                        fputcsv($handle, [
                            $report->id,
                            $report->status?->value,
                            $report->reporter_name,
                            $report->reporter_email,
                            $report->link,
                            $report->normalized_link,
                            $report->details,
                            $report->public_note,
                            $report->is_published ? '1' : '0',
                            optional($report->confirmed_at)?->toDateTimeString(),
                            $report->duplicate_of_id,
                            optional($report->created_at)?->toDateTimeString(),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
