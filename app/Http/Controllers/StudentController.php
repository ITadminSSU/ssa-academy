<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateStudentProfileRequest;
use App\Services\AuthService;
use App\Models\Course\WatchHistory;
use App\Services\Course\CourseCategoryService;
use App\Services\Course\CourseCertificateIssuanceService;
use App\Services\Course\CourseCompletionGateService;
use App\Services\Course\CourseEnrollmentService;
use App\Services\Course\CourseFinalExamService;
use App\Services\Course\CoursePlayerService;
use App\Services\Course\CourseService;
use App\Services\Course\CourseWishlistService;
use App\Services\Payment\SubscriptionAccessService;
use App\Services\StudentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamAttempt;
use Modules\Exam\Services\ExamAttemptService;
use Modules\Exam\Services\ExamEnrollmentService;
use Modules\Exam\Services\ExamQuantityTakeoffService;

class StudentController extends Controller
{
    public function __construct(
        protected StudentService $studentService,
        protected CoursePlayerService $coursePlayerService,
        protected CourseCompletionGateService $courseCompletionGateService,
        protected CourseCertificateIssuanceService $certificateIssuance,
        protected CourseEnrollmentService $enrollmentService,
        protected ExamEnrollmentService $examEnrollment,
        protected ExamAttemptService $examAttempt,
        protected CourseService $courseService,
        protected CourseCategoryService $categoryService,
        protected CourseWishlistService $wishlistService,
        protected CourseFinalExamService $courseFinalExamService,
        protected SubscriptionAccessService $subscriptionAccess,
    ) {}

    /**
     * Display the student profile page.
     */
    public function index(Request $request, string $tab)
    {
        if (!in_array($tab, ['home', 'courses'], true) && !$request->user()->hasVerifiedEmail()) {
            return redirect()
                ->to(app(AuthService::class)->homeUrlFor($request->user(), ['tab' => 'home']))
                ->with('error', 'Please verify your email address.');
        }

        $props = $this->studentService->getStudentData($tab, $request->only(['filter', 'course_id']));

        return Inertia::render('student/index', [
            ...$props,
            'tab' => $tab,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function browse_category(Request $request, string $category)
    {
        $user = Auth::user();

        $categoryModel = $category !== 'all' ? $this->categoryService->getCategoryBySlug($category) : null;

        $courses = $this->courseService->getCourses([
            ...$request->all(),
            'per_page' => 12,
            'category' => $category,
            'status' => 'approved',
            'catalog' => true,
        ], $user, true);

        $wishlists = $this->wishlistService->getWishlists(['user_id' => $user?->id]);
        $levels = \App\Enums\CourseLevelType::cases();
        $prices = \App\Enums\CoursePricingType::cases();
        $categories = $this->categoryService->getCategories()['categories'];

        return Inertia::render('student/category', [
            'category' => $categoryModel,
            'categorySlug' => $category,
            'categoryChild' => null,
            'courses' => $courses,
            'wishlists' => $wishlists,
            'categories' => $categories,
            'levels' => $levels,
            'prices' => $prices,
        ]);
    }

    public function show_course(int $id, string $tab)
    {
        if ($tab === 'live_classes') {
            return redirect()->route('student.course.show', ['id' => $id, 'tab' => 'modules']);
        }

        $user = Auth::user();
        $course = $this->studentService->getEnrolledCourse($id, $user);
        $props = $this->studentService->getEnrolledCourseOverview($id, $tab, $user);
        $watchHistory = $this->coursePlayerService->getWatchHistory($id, $user->id);
        $completion = $this->coursePlayerService->calculateCompletion($course, $watchHistory);
        $courseGates = $this->courseCompletionGateService->getGateStatus($course, $user->id, $completion, $watchHistory);
        $subscriptionAccess = $this->subscriptionAccess->toFrontendPayload($user, $course);

        if ($tab === 'assignments' && !$courseGates['assignments_unlocked']) {
            return redirect()
                ->route('student.course.show', ['id' => $id, 'tab' => 'modules'])
                ->with('error', 'Complete all video lessons before accessing assignments.');
        }

        if ($tab === 'quizzes' && !$courseGates['quizzes_unlocked']) {
            return redirect()
                ->route('student.course.show', ['id' => $id, 'tab' => 'assignments'])
                ->with('error', 'Submit assignments and receive trainer approval before accessing quizzes.');
        }

        $certificate = null;
        if ($tab === 'certificate' && !$courseGates['certificate_unlocked']) {
            $props['certificateTemplate'] = null;
            $props['marksheetTemplate'] = null;
            $props['studentMarks'] = null;
        } elseif ($tab === 'certificate' && $courseGates['certificate_unlocked']) {
            $certificate = $this->certificateIssuance->issueForCourse($course, $user);
        }

        return Inertia::render('student/course', [
            ...$props,
            'tab' => $tab,
            'course' => $course,
            'watchHistory' => $watchHistory,
            'completion' => $completion,
            'courseGates' => $courseGates,
            'certificate' => $certificate,
            'subscriptionAccess' => $subscriptionAccess,
        ]);
    }

    public function show_exam(Request $request, int $id, string $tab)
    {
        $user = Auth::user();
        $this->courseFinalExamService->ensureEnrollmentForExam($id, $user);
        $exam = $this->examEnrollment->getEnrolledExam($id, $user);
        $exam->makeHidden(['takeoff_config']);
        $attempts = $this->examAttempt->getExamAttempts(['exam_id' => $id, 'user_id' => $user->id]);
        $bestAttempt = $this->examAttempt->getBestExamAttempt($id, $user->id);
        $props = $this->studentService->getEnrolledExamTabProps($id, $tab, $user);
        $tutorialVideo = null;

        if ($exam->exam_mode === 'quantity_takeoff') {
            $tutorialVideo = app(ExamQuantityTakeoffService::class)
                ->publicTutorialVideo(Exam::query()->find($id));
        }

        if ($tab === 'resources') {
            $exam->load('resources');
        }

        if ($tab === 'attempts' && $request->attempt) {
            $attemptId = (int) $request->attempt;
            $existingAttempt = ExamAttempt::query()->find($attemptId);

            if ($existingAttempt && $existingAttempt->status === 'submitted') {
                $this->examAttempt->finalizeSubmittedAttempt($existingAttempt);
            }

            $props['attempt'] = $this->examAttempt->getExamAttempt($attemptId, ['user_id' => $user->id]);
            $props['attempt']?->exam?->makeHidden(['takeoff_config']);
        }

        return Inertia::render('student/exam/index', [
            ...$props,
            'tab' => $tab,
            'exam' => $exam,
            'attempts' => $attempts,
            'bestAttempt' => $bestAttempt,
            'tutorialVideo' => $tutorialVideo,
        ]);
    }

    /**
     * Update the authenticated student's profile information.
     */
    public function update_profile(UpdateStudentProfileRequest $request)
    {
        $this->studentService->updateProfile($request->validated(), Auth::user()->id);

        return redirect()->back()->with('success', 'Profile updated successfully');
    }
}
