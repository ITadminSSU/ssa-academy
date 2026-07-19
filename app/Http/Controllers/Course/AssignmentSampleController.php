<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\Course\AssignmentSampleDownload;
use App\Models\Course\CourseAssignment;
use App\Services\Course\CourseCompletionGateService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AssignmentSampleController extends Controller
{
    public function __construct(private CourseCompletionGateService $gateService) {}

    public function download(CourseAssignment $assignment)
    {
        $user = Auth::user();
        $assignment->load('course');

        if (!$this->gateService->canAccessAssignmentTab($assignment->course, $user->id)) {
            return back()->with('error', 'Complete all video lessons before downloading assignment materials.');
        }

        if (empty($assignment->sample_project_path)) {
            abort(404, 'No sample project configured for this assignment.');
        }

        AssignmentSampleDownload::updateOrCreate(
            [
                'user_id' => $user->id,
                'course_assignment_id' => $assignment->id,
            ],
            ['downloaded_at' => now()],
        );

        if ($assignment->sample_project_type === 'file') {
            if (!Storage::disk('public')->exists($assignment->sample_project_path)) {
                abort(404, 'Sample project file not found.');
            }

            return Storage::disk('public')->download(
                $assignment->sample_project_path,
                basename($assignment->sample_project_path),
            );
        }

        return redirect()->away($assignment->sample_project_path);
    }
}
