<?php

namespace App\Http\Controllers\Course;

use App\Enums\CoursePricingType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseEnrollmentRequest;
use App\Models\Course\Course;
use App\Services\Course\CourseEnrollmentService;
use App\Services\Course\CourseService;
use App\Services\LegalAgreementService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CourseEnrollmentController extends Controller
{
    public function __construct(
        private UserService $user,
        private CourseService $course,
        private CourseEnrollmentService $courseEnrollment,
        private LegalAgreementService $legalAgreement,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $data = array_merge(
            $request->all(),
            isAdmin() ? [] : (
                $user->instructor ?
                ['instructor_id' => $user->instructor->id] :
                ['user_id' => $user->id])
        );

        $prices = array_map(
            static fn (CoursePricingType $case): string => $case->value,
            CoursePricingType::cases(),
        );
        $users = $this->user->getUsers([]);
        $courses = $this->enrollmentCourseOptions($user);
        $enrollments = $this->courseEnrollment->getEnrollments($data, true);

        return Inertia::render('dashboard/enrollments/courses', [
            'prices' => $prices,
            'users' => $users,
            'courses' => $courses,
            'enrollments' => $enrollments,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $prices = array_map(
            static fn (CoursePricingType $case): string => $case->value,
            CoursePricingType::cases(),
        );
        $users = $this->user->getUsers([]);
        $courses = $this->enrollmentCourseOptions(Auth::user());

        return Inertia::render('dashboard/enrollments/create', [
            'prices' => $prices,
            'users' => $users,
            'courses' => $courses,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCourseEnrollmentRequest $request)
    {
        if ($this->legalAgreement->requiresAcceptance($request->user())) {
            return back()->with('error', 'Accept the Terms & Conditions and NDA before enrolling in courses.');
        }

        try {
            $this->courseEnrollment->createCourseEnroll($request->validated());

            return back()->with('success', 'Enrollment is successfully done in this course');
        } catch (\InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->courseEnrollment->deleteEnrollment($id);

        return back()->with('success', 'Enrollment is successfully deleted');
    }

    /**
     * Lightweight course list for enrollment UI (id + title only).
     * Avoids loading full curricula, which can break Inertia JSON responses.
     *
     * @return \Illuminate\Support\Collection<int, Course>
     */
    private function enrollmentCourseOptions(?\App\Models\User $user)
    {
        return Course::query()
            ->select(['id', 'title', 'instructor_id', 'status'])
            ->where('status', 'approved')
            ->when(
                $user && ! isAdmin() && $user->instructor,
                fn ($query) => $query->where('instructor_id', $user->instructor->id),
            )
            ->orderBy('title')
            ->get();
    }
}
