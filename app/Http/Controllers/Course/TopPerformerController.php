<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\Services\Course\TopPerformerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TopPerformerController extends Controller
{
    public function __construct(
        private TopPerformerService $topPerformerService,
        private AuthService $authService,
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();

        if (! in_array($user->role, ['admin', 'instructor'], true)) {
            return redirect($this->authService->homeUrlFor($user, ['tab' => 'courses']));
        }

        $performers = $this->topPerformerService->getLeaderboard($user, $request->all());

        return Inertia::render('dashboard/top-performers/index', [
            'performers' => $performers,
            'top_limit' => TopPerformerService::TOP_PERFORMER_RANK_LIMIT,
            'audience' => in_array($user->role, ['admin', 'instructor'], true) ? 'staff' : 'learner',
        ]);
    }
}
