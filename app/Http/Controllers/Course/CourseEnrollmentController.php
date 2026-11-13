<?php

namespace App\Http\Controllers\Course;

use App\Enums\CoursePricingType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseEnrollmentRequest;
use App\Models\Course\Course;
use App\Models\StudentLabel;
use App\Services\Course\CourseEnrollmentService;
use App\Services\Course\CourseService;
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
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $data = $this->enrollmentListFilters($request);

        $prices = array_map(
            static fn (CoursePricingType $case): string => $case->value,
            CoursePricingType::cases(),
        );
        $users = $this->user->getEnrollmentPickerUsers();
        $courses = $this->enrollmentCourseOptions($user);
        $enrollments = $this->courseEnrollment->getEnrollments($data, true, true);
        $chartScope = array_filter(
            [
                'instructor_id' => $data['instructor_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'label_ids' => $data['label_ids'] ?? null,
                'course_id' => $data['course_id'] ?? null,
                'coupon' => $data['coupon'] ?? null,
            ],
            static fn ($value) => $value !== null && $value !== '',
        );
        $couponScope = array_filter(
            [
                'instructor_id' => $data['instructor_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
            ],
            static fn ($value) => $value !== null && $value !== '',
        );

        return Inertia::render('dashboard/enrollments/courses', [
            'prices' => $prices,
            'users' => $users,
            'courses' => $courses,
            'enrollments' => $enrollments,
            'enrollmentChart' => $this->courseEnrollment->enrollmentCountsByCourse($chartScope),
            'studentLabels' => StudentLabel::query()->orderBy('name')->get(['id', 'name', 'color']),
            'couponCodes' => $this->courseEnrollment->couponCodesInScope($couponScope),
            'matchingEnrollmentIds' => $this->courseEnrollment->matchingEnrollmentIds($data),
        ]);
    }

    public function export(Request $request)
    {
        return $this->courseEnrollment->exportEnrollmentsCsv($this->enrollmentListFilters($request));
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
        $users = $this->user->getEnrollmentPickerUsers();
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

    /**
     * @return array<string, mixed>
     */
    private function enrollmentListFilters(Request $request): array
    {
        $user = Auth::user();

        $search = trim((string) $request->input('search', ''));
        $perPage = $request->input('per_page');
        $labelIds = $this->selectedLabelIds($request);
        $sort = (string) $request->input('sort', '');
        $sorts = ['course', 'enrolled_date', 'coupon', 'tags'];

        $filters = array_filter(
            [
                'search' => $search !== '' ? $search : null,
                'per_page' => $perPage,
            ],
            static fn ($value) => $value !== null && $value !== '',
        );

        if ($labelIds !== []) {
            $filters['label_ids'] = $labelIds;
        }

        $courseId = (int) $request->input('course', 0);
        if ($courseId > 0) {
            $filters['course_id'] = $courseId;
        }

        $coupon = trim((string) $request->input('coupon', ''));
        if (strtolower($coupon) === 'none') {
            $filters['coupon'] = 'none';
        } elseif ($coupon !== '') {
            $filters['coupon'] = strtoupper($coupon);
        }

        if (in_array($sort, $sorts, true)) {
            $filters['sort'] = $sort;
            $filters['direction'] = $request->input('direction') === 'desc' ? 'desc' : 'asc';
        }

        return array_merge(
            $filters,
            isAdmin() ? [] : (
                $user?->instructor
                    ? ['instructor_id' => $user->instructor->id]
                    : ['user_id' => $user?->id]
            )
        );
    }

    /**
     * @return list<int>
     */
    private function selectedLabelIds(Request $request): array
    {
        $raw = $request->input('tags', '');
        $parts = is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $parts),
            static fn (int $id) => $id > 0,
        )));
    }
}
