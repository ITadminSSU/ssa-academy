<?php

namespace App\Http\Controllers;

use App\Enums\ScamTiplineStatus;
use App\Models\ScamTiplineReport;
use App\Services\ScamTiplineService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class FraudTrainingTiplineController extends Controller
{
    public function __construct(private ScamTiplineService $tipline) {}

    public function show(Request $request)
    {
        $warningsQuery = ScamTiplineReport::query()
            ->publishedWarnings()
            ->when($request->string('q')->trim()->isNotEmpty(), function ($query) use ($request) {
                $q = '%'.$request->string('q')->trim().'%';
                $query->where(function ($inner) use ($q) {
                    $inner->where('link', 'like', $q)
                        ->orWhere('public_note', 'like', $q)
                        ->orWhere('normalized_link', 'like', $q);
                });
            });

        $sort = $request->string('sort')->toString() === 'oldest' ? 'asc' : 'desc';

        $warnings = $warningsQuery
            ->orderBy('confirmed_at', $sort)
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ScamTiplineReport $report) => [
                'id' => $report->id,
                'link' => $report->link,
                'public_note' => $report->public_note,
                'confirmed_at' => optional($report->confirmed_at)?->timezone(config('app.timezone'))->toDateString(),
                'share_url' => $report->share_url,
            ]);

        return Inertia::render('intro/fraud-training-tipline', [
            'warnings' => $warnings,
            'filters' => [
                'q' => $request->string('q')->toString(),
                'sort' => $sort === 'asc' ? 'oldest' : 'newest',
            ],
            'flashSuccess' => $request->session()->get('success'),
        ]);
    }

    public function store(Request $request)
    {
        // Honeypot — bots fill this; humans leave it empty.
        if (filled($request->input('website'))) {
            return redirect()
                ->route('fraud-training-tipline')
                ->with('success', 'Thank you. Your tip was received and will be reviewed.');
        }

        $data = $request->validate([
            'reporter_name' => ['nullable', 'string', 'max:255'],
            'reporter_email' => ['nullable', 'email', 'max:255'],
            'link' => ['nullable', 'string', 'max:2048'],
            'details' => ['nullable', 'string', 'max:5000'],
            'screenshot' => ['nullable', 'image', 'max:5120'],
        ]);

        if (
            blank($data['reporter_name'] ?? null)
            && blank($data['reporter_email'] ?? null)
            && blank($data['link'] ?? null)
            && blank($data['details'] ?? null)
            && ! $request->hasFile('screenshot')
        ) {
            return redirect()
                ->back()
                ->withErrors(['link' => 'Please share a link, details, or a screenshot so we can review the tip.'])
                ->withInput();
        }

        $this->tipline->submitPublicTip($data, $request, $request->file('screenshot'));

        return redirect()
            ->route('fraud-training-tipline')
            ->with('success', 'Thank you. Your tip was received and will be reviewed by our team.');
    }

    public function warning(ScamTiplineReport $report)
    {
        abort_unless(
            $report->status === ScamTiplineStatus::Confirmed && $report->is_published,
            404
        );

        return Inertia::render('intro/fraud-training-tipline-warning', [
            'warning' => [
                'id' => $report->id,
                'link' => $report->link,
                'public_note' => $report->public_note,
                'confirmed_at' => optional($report->confirmed_at)?->timezone(config('app.timezone'))->toDateString(),
                'share_url' => $report->share_url,
            ],
        ]);
    }
}
