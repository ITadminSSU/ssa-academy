<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentProfileDocumentRequest;
use App\Http\Requests\UpdateStudentExperienceRequest;
use App\Http\Requests\UpdateStudentProfileRequest;
use App\Services\AuthService;
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
use App\Services\UsExperience\UsExperienceCertificateIssuanceService;
use App\Services\UsExperience\UsExperienceUnlockService;
use App\Support\CourseWelcomeEmailCopy;
use App\Support\S3CompatibleStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamAttempt;
use Modules\Exam\Services\ExamAttemptService;
use Modules\Exam\Services\ExamEnrollmentService;
use Modules\Exam\Services\ExamQuantityTakeoffService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
        protected UsExperienceUnlockService $usExperienceUnlock,
        protected UsExperienceCertificateIssuanceService $usExperienceCertificates,
    ) {}

    /**
     * Display the student profile page.
     */
    public function index(Request $request, string $tab)
    {
        if (! in_array($tab, ['home', 'courses'], true) && ! $request->user()->hasVerifiedEmail()) {
            return redirect()
                ->to(app(AuthService::class)->homeUrlFor($request->user(), ['tab' => 'home']))
                ->with('error', 'Please verify your email address.');
        }

        $props = $this->studentService->getStudentData($tab, $request->only(['filter', 'course_id']));

        if ($tab === 'profile') {
            $props['profileDocuments'] = $this->profileDocuments($request->user());
        }

        return Inertia::render('student/index', [
            ...$props,
            'tab' => $tab,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function dismiss_dashboard_welcome_overlay(Request $request)
    {
        $validated = $request->validate([
            'version' => 'required|string|max:64',
            'campaign_id' => 'nullable|integer|min:1',
        ]);

        $this->studentService->dismissDashboardWelcomeOverlay(
            $request->user(),
            $validated['version'],
            isset($validated['campaign_id']) ? (int) $validated['campaign_id'] : null,
        );

        return back();
    }

    public function browse_category(Request $request, string $category)
    {
        $user = Auth::user();

        $categoryModel = $category !== 'all' ? $this->categoryService->getCategoryBySlug($category) : null;

        $courses = $this->courseService->getCourses([
            ...$request->all(),
            'per_page' => 12,
            'category' => $category,
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
        $course->loadMissing('course_category');
        $showUsExperience = CourseWelcomeEmailCopy::showsUsExperience($course);

        if ($tab === 'assignments') {
            return redirect()->route('student.course.show', [
                'id' => $id,
                'tab' => $showUsExperience ? 'us-experience' : 'modules',
            ]);
        }

        if ($tab === 'us-experience' && ! $showUsExperience) {
            return redirect()->route('student.course.show', ['id' => $id, 'tab' => 'modules']);
        }

        $enrollment = \App\Models\Course\CourseEnrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $id)
            ->first();

        // Reserved / coming-soon seats: show public course details (pay balance / launch info), not the learner modules UI.
        if (
            ($enrollment && $enrollment->isReservedSeat())
            || ($course && $course->isComingSoon() && ! $this->subscriptionAccess->canAccessPlayer($user, $course))
        ) {
            return redirect()
                ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                ->with(
                    'info',
                    $enrollment?->isReservedSeat()
                        ? 'Your seat is reserved. Full course access unlocks after you pay the remaining balance.'
                        : 'This course is coming soon. You can view details here until launch.',
                );
        }

        $props = $this->studentService->getEnrolledCourseOverview($id, $tab, $user);
        $watchHistory = $this->coursePlayerService->getWatchHistory($id, $user->id);
        $completion = $this->coursePlayerService->calculateCompletion($course, $watchHistory);
        $courseGates = $this->courseCompletionGateService->getGateStatus($course, $user->id, $completion, $watchHistory);
        $subscriptionAccess = $this->subscriptionAccess->toFrontendPayload($user, $course);

        if ($tab === 'quizzes' && ! $courseGates['quizzes_unlocked'] && ! $course->isStaffPreviewer($user)) {
            return redirect()
                ->route('student.course.show', ['id' => $id, 'tab' => 'modules'])
                ->with('error', 'Complete all video lessons before accessing quizzes.');
        }

        $certificate = null;
        $certificateRelease = null;
        if ($tab === 'certificate' && ! $courseGates['certificate_unlocked']) {
            $props['certificateTemplate'] = null;
            $props['marksheetTemplate'] = null;
            $props['studentMarks'] = null;
        } elseif ($tab === 'certificate' && $courseGates['certificate_unlocked']) {
            $certificate = $this->certificateIssuance->issueForCourse($course, $user);

            if (! $certificate) {
                $certificateRelease = $this->certificateIssuance->missingReleaseDocuments($user);
            }
        }

        $usExperience = null;
        if ($tab === 'us-experience' && $showUsExperience) {
            $usExperience = $this->usExperienceUnlock->studentOverview($course, $user);

            if (! empty($usExperience['course_complete'])) {
                $usExperience['certificates'] = $this->usExperienceCertificates->forStudent($course, $user);
            }
        }

        return Inertia::render('student/course', [
            ...$props,
            'tab' => $tab,
            'course' => $course,
            'watchHistory' => $watchHistory,
            'completion' => $completion,
            'courseGates' => $courseGates,
            'certificate' => $certificate,
            'certificateRelease' => $certificateRelease,
            'subscriptionAccess' => $subscriptionAccess,
            'showUsExperience' => $showUsExperience,
            'usExperience' => $usExperience,
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
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo');
        } else {
            unset($data['photo']);
        }

        $user = $this->studentService->updateProfile($data, (string) Auth::id());

        Auth::setUser($user);

        return redirect()->back()->with('success', 'Changes saved');
    }

    public function updateOwnExperience(UpdateStudentExperienceRequest $request)
    {
        $user = $request->user();
        $user->has_us_estimating_experience = $request->boolean('has_us_estimating_experience');
        $user->save();

        return response()->json([
            'has_us_estimating_experience' => (bool) $user->has_us_estimating_experience,
        ]);
    }

    public function storeOwnDocument(StoreStudentProfileDocumentRequest $request, string $type)
    {
        [$collection, $field] = $this->profileDocumentTarget($type);
        $user = $request->user();

        $user->addMediaFromRequest($field)
            ->withCustomProperties(['name' => $collection])
            ->toMediaCollection($collection);
        $user->unsetRelation('media');

        return response()->json([
            'document' => $this->profileDocumentLink($user->fresh() ?? $user, $collection, $type),
        ]);
    }

    public function destroyOwnDocument(Request $request, string $type)
    {
        [$collection] = $this->profileDocumentTarget($type);
        $request->user()?->clearMediaCollection($collection);

        return response()->json([
            'document' => null,
        ]);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function profileDocumentTarget(string $type): array
    {
        return match ($type) {
            'cv' => ['cv_resume', 'cv_resume', 'CV/Resume'],
            'id' => ['id_document', 'id_document', 'ID document'],
            'assessment' => ['criteria_assessment', 'criteria_assessment', 'Assessment result'],
            default => abort(404),
        };
    }

    public function showOwnDocument(Request $request, string $type)
    {
        [$collection, , $label] = $this->profileDocumentTarget($type);

        $media = $request->user()?->getFirstMedia($collection);

        if (! $media) {
            abort(404, $label.' not found');
        }

        $fileName = str_replace(['"', '\\', "\r", "\n"], '', (string) $media->file_name);
        $mimeType = $media->mime_type ?: 'application/octet-stream';
        $disposition = 'inline; filename="'.$fileName.'"';

        if ($this->mediaUsesCloudDisk($media)) {
            $fileUrl = $this->inlineCloudUrl($media, $fileName);

            if ($fileUrl === '') {
                abort(404, $label.' file not found');
            }

            return redirect()->away($fileUrl);
        }

        $localPath = $media->getPath();
        if (is_string($localPath) && $localPath !== '' && is_file($localPath)) {
            return response()->file($localPath, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => $disposition,
            ]);
        }

        abort(404, $label.' file not found');
    }

    private function mediaUsesCloudDisk(Media $media): bool
    {
        $disk = (string) $media->disk;

        return $disk === 's3' || config("filesystems.disks.{$disk}.driver") === 's3';
    }

    /**
     * Signed inline URL for a file on Cloudflare R2 or another S3-compatible disk.
     * An unsigned object URL 404s when the bucket is private.
     */
    private function inlineCloudUrl(Media $media, string $fileName): string
    {
        $mimeType = $media->mime_type ?: 'application/octet-stream';

        try {
            return $media->getTemporaryUrl(now()->addHours(12), '', [
                'ResponseContentType' => $mimeType,
                'ResponseContentDisposition' => 'inline; filename="'.$fileName.'"',
            ]);
        } catch (\Throwable) {
            $key = ltrim((string) $media->getPathRelativeToRoot(), '/');

            if ($key === '') {
                $key = ltrim($media->id.'/'.$media->file_name, '/');
            }

            return S3CompatibleStorage::temporaryObjectUrl($key);
        }
    }

    /**
     * @return array{cv: ?array{file_name: string, view_url: string}, id: ?array{file_name: string, view_url: string}, assessment: ?array{file_name: string, view_url: string}}
     */
    private function profileDocuments(\App\Models\User $user): array
    {
        return [
            'cv' => $this->profileDocumentLink($user, 'cv_resume', 'cv'),
            'id' => $this->profileDocumentLink($user, 'id_document', 'id'),
            'assessment' => $this->profileDocumentLink($user, 'criteria_assessment', 'assessment'),
        ];
    }

    /**
     * @return array{file_name: string, view_url: string}|null
     */
    private function profileDocumentLink(\App\Models\User $user, string $collection, string $type): ?array
    {
        $media = $user->getFirstMedia($collection);

        if (! $media) {
            return null;
        }

        return [
            'file_name' => $media->file_name,
            'view_url' => route('student.profile.document', ['type' => $type]),
        ];
    }

    public function storeCertificateDocuments(Request $request, int $id)
    {
        $user = $request->user();
        $course = $this->studentService->getEnrolledCourse((string) $id, $user);
        $missing = $this->certificateIssuance->missingReleaseDocuments($user);

        $rules = [];

        $maxKilobytes = UpdateStudentProfileRequest::PROFILE_DOCUMENT_MAX_KILOBYTES;

        if ($missing['needs_cv']) {
            $rules['cv_resume'] = 'required|file|mimes:pdf,doc,docx|max:'.$maxKilobytes;
        } elseif ($request->hasFile('cv_resume')) {
            $rules['cv_resume'] = 'file|mimes:pdf,doc,docx|max:'.$maxKilobytes;
        }

        if ($missing['needs_id']) {
            $rules['id_document'] = 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:'.$maxKilobytes;
        } elseif ($request->hasFile('id_document')) {
            $rules['id_document'] = 'file|mimes:jpg,jpeg,png,webp,pdf|max:'.$maxKilobytes;
        }

        $request->validate($rules, [
            'cv_resume.required' => 'Please upload your CV or resume.',
            'cv_resume.mimes' => 'CV / resume must be a PDF, DOC, or DOCX file.',
            'cv_resume.max' => 'CV / resume must not be larger than 5 GB.',
            'id_document.required' => 'Please upload a government-issued ID for verification.',
            'id_document.mimes' => 'ID must be a JPG, PNG, WEBP, or PDF file.',
            'id_document.max' => 'ID must not be larger than 5 GB.',
        ]);

        if ($request->hasFile('cv_resume')) {
            $user->addMediaFromRequest('cv_resume')
                ->withCustomProperties(['name' => 'cv_resume'])
                ->toMediaCollection('cv_resume');
        }

        if ($request->hasFile('id_document')) {
            $user->addMediaFromRequest('id_document')
                ->withCustomProperties(['name' => 'id_document'])
                ->toMediaCollection('id_document');
        }

        $user = $user->fresh();
        Auth::setUser($user);

        $watchHistory = $this->coursePlayerService->getWatchHistory($id, $user->id);
        $completion = $this->coursePlayerService->calculateCompletion($course, $watchHistory);
        $gates = $this->courseCompletionGateService->getGateStatus($course, $user->id, $completion, $watchHistory);
        $certificate = $gates['certificate_unlocked']
            ? $this->certificateIssuance->issueForCourse($course, $user)
            : null;
        $stillMissing = $this->certificateIssuance->missingReleaseDocuments($user);

        $message = $certificate
            ? 'Your certificate is ready.'
            : (($stillMissing['needs_cv'] || $stillMissing['needs_id'])
                ? 'File saved. Upload the remaining document to release your certificate.'
                : 'Your documents are saved. They will be used for every course.');

        return redirect()
            ->route('student.course.show', ['id' => $id, 'tab' => 'certificate'])
            ->with('success', $message);
    }
}
