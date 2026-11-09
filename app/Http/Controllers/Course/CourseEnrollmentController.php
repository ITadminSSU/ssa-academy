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
            ],
            static fn ($value) => $value !== null && $value !== '',
        );
        $chartFilter = $this->chartTagFilter($request);

        return Inertia::render('dashboard/enrollments/courses', [
            'prices' => $prices,
            'users' => $users,
            'courses' => $courses,
            'enrollments' => $enrollments,
            'enrollmentOverview' => $this->courseEnrollment->enrollmentOverview(
                $chartScope,
                $chartFilter['label_ids'],
                $chartFilter['pef'],
            ),
            'chartFilter' => $chartFilter,
            'studentLabels' => StudentLabel::query()->orderBy('name')->get(['id', 'name', 'color']),
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
     * Tag filters apply to the chart and summary only. The course list ignores them.
     *
     * @return array{label_ids: list<int>, pef: bool}
     */
    private function chartTagFilter(Request $request): array
    {
        $raw = $request->input('chart_labels', '');

        if (is_array($raw)) {
            $raw = implode(',', $raw);
        }

        $labelIds = collect(explode(',', (string) $raw))
            ->map(fn ($id) => (int) trim((string) $id))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        return [
            'label_ids' => $labelIds,
            'pef' => $request->boolean('chart_pef'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function enrollmentListFilters(Request $request): array
    {
        $user = Auth::user();

        $search = trim((string) $request->input('search', ''));
        $perPage = $request->input('per_page');

        return array_merge(
            array_filter(
                [
                    'search' => $search !== '' ? $search : null,
                    'per_page' => $perPage,
                ],
                static fn ($value) => $value !== null && $value !== '',
            ),
            isAdmin() ? [] : (
                $user?->instructor
                    ? ['instructor_id' => $user->instructor->id]
                    : ['user_id' => $user?->id]
            )
        );
    }
}
