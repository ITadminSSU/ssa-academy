<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectSubmission;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectSubmissionController extends Controller
{
    public function __construct(protected MediaService $mediaService) {}

    /**
     * Learner submits (or re-submits) their completed work for a project.
     */
    public function store(Request $request, Project $project)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:51200'],
        ]);

        $submission = ProjectSubmission::updateOrCreate(
            ['project_id' => $project->id, 'user_id' => Auth::id()],
            [
                'submitted_at' => now(),
                // A fresh upload resets any previous review.
                'score' => null,
                'feedback' => null,
                'scored_by' => null,
                'scored_at' => null,
            ]
        );

        $file = $request->file('file');
        $url = $this->mediaService->addNewDeletePrev($submission, $file, 'file');

        $submission->update([
            'file' => $url,
            'file_name' => $file->getClientOriginalName(),
        ]);

        return redirect()->back()->with('success', 'Your work has been submitted');
    }

    /**
     * Trainer/admin scores a submission and leaves feedback.
     */
    public function score(Request $request, ProjectSubmission $submission)
    {
        $data = $request->validate([
            'score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        $submission->update([
            'score' => $data['score'] ?? null,
            'feedback' => $data['feedback'] ?? null,
            'scored_by' => Auth::id(),
            'scored_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Submission reviewed');
    }
}
