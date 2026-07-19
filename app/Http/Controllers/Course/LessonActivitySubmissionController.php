<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLessonActivitySubmissionRequest;
use App\Http\Requests\UpdateAssignmentSubmissionRequest;
use App\Services\Course\LessonActivitySubmissionService;
use Illuminate\Support\Facades\Auth;

class LessonActivitySubmissionController extends Controller
{
    public function __construct(private LessonActivitySubmissionService $submissionService) {}

    public function store(StoreLessonActivitySubmissionRequest $request)
    {
        $this->submissionService->submit([
            ...$request->validated(),
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Activity submitted successfully. Your trainer will review it soon.');
    }

    public function update(UpdateAssignmentSubmissionRequest $request, string $id)
    {
        $this->submissionService->grade([
            ...$request->validated(),
        ], $id);

        return back()->with('success', 'Activity graded successfully.');
    }
}
