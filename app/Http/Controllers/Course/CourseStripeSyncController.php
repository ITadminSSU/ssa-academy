<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Services\Course\CourseService;
use App\Services\Payment\CourseStripeSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseStripeSyncController extends Controller
{
    public function __construct(
        private CourseService $courseService,
        private CourseStripeSyncService $courseStripeSync,
    ) {}

    public function __invoke(Request $request, string $id)
    {
        $user = Auth::user();
        $course = $this->courseService->getUserCourseById($id, $user);

        if (!$course) {
            abort(404);
        }

        if (!isAdmin() && (int) $user->instructor_id !== (int) $course->instructor_id) {
            abort(403);
        }

        try {
            $this->courseStripeSync->sync($course);

            return back()->with('success', 'Course subscription synced to Stripe successfully.');
        } catch (\Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }
}
