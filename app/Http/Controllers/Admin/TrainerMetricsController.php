<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\TrainerMetricsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TrainerMetricsController extends Controller
{
    public function __construct(private TrainerMetricsService $trainerMetrics) {}

    public function index(Request $request)
    {
        return Inertia::render('dashboard/admin/trainer-metrics/index', [
            'trainers' => $this->trainerMetrics->getTrainerMetrics($request->all()),
            'summary' => $this->trainerMetrics->getPlatformSummary(),
            'sort_by' => $request->get('sort_by', 'enrollments'),
            'filters' => [
                'search' => $request->get('search', ''),
            ],
        ]);
    }
}
