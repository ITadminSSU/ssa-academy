<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\Course\Course;
use App\Services\Course\CourseStudentProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CourseStudentProgressController extends Controller
{
    public function __construct(private CourseStudentProgressService $progressService) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $courses = $this->progressService->getCoursesForProgress($user, $request->all());
        $summary = $this->progressService->getTrackingDashboardSummary($user);

        return Inertia::render('dashboard/student-progress/index', compact('courses', 'summary'));
    }

    public function show(Request $request, Course $course)
    {
        $user = Auth::user();
        $this->progressService->authorizeCourseAccess($course, $user);

        $progress = $this->progressService->getCourseStudentProgress($course, $request->all());

        return Inertia::render('dashboard/student-progress/show', [
            'course' => $progress['course'],
            'students' => $progress['students'],
            'enrollments' => $progress['enrollments'],
            'summary' => $progress['summary'],
            'sort_by' => $progress['sort_by'],
        ]);
    }
}
