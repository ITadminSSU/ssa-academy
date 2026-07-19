<?php

namespace Modules\Exam\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Modules\Exam\Models\ExamAttempt;

class ExamLeaderboardService
{
    public function getLeaderboard(array $data = []): LengthAwarePaginator
    {
        $perPage = (int) ($data['per_page'] ?? 20);
        $search = $data['search'] ?? null;
        $examId = $data['exam_id'] ?? null;

        $query = ExamAttempt::query()
            ->with(['user:id,name,email', 'exam:id,title,total_marks,pass_mark'])
            ->whereIn('status', ['completed', 'submitted'])
            ->when($examId, fn ($q) => $q->where('exam_id', $examId))
            ->when($search, function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('obtained_marks')
            ->orderByDesc('end_time');

        $page = max(1, (int) ($data['page'] ?? Paginator::resolveCurrentPage()));

        return $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();
    }
}
