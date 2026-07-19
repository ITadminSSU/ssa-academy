<?php

namespace Modules\Exam\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Modules\Exam\Models\Exam;
use Modules\Exam\Services\ExamLeaderboardService;

class ExamLeaderboardController extends Controller
{
    public function __construct(private ExamLeaderboardService $leaderboardService) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $examQuery = Exam::query()->where('status', 'approved');

        if (!isAdmin()) {
            $examQuery->where('instructor_id', $user->instructor_id);
        }

        $attempts = $this->leaderboardService->getLeaderboard($request->all());

        return Inertia::render('dashboard/exams/leaderboard', [
            'attempts' => $attempts,
            'exams' => $examQuery->orderBy('title')->get(['id', 'title']),
            'filters' => [
                'search' => $request->get('search', ''),
                'exam_id' => $request->get('exam_id', ''),
            ],
        ]);
    }
}
