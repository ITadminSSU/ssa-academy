<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\Course\SectionLesson;
use App\Models\Course\WatchHistory;
use App\Services\Course\CourseCertificateIssuanceService;
use App\Services\Course\CourseCompletionGateService;
use App\Services\Course\CourseFinalExamService;
use App\Services\Course\CoursePlayerService;
use App\Services\Course\CourseReviewService;
use App\Services\Course\CourseService;
use App\Services\Course\CourseSectionService;
use App\Services\Course\LessonWatchProgressService;
use App\Services\Course\ProtectedMediaService;
use App\Services\Course\VideoPlaybackTokenService;
use App\Services\LiveClass\ZoomLiveService;
use App\Services\Payment\SubscriptionAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PlayerController extends Controller
{
    public function __construct(
        protected CourseService $courseService,
        protected CourseCompletionGateService $courseCompletionGateService,
        protected CoursePlayerService $coursePlay,
        protected CourseSectionService $sectionService,
        protected CourseReviewService $reviewService,
        protected ZoomLiveService $zoomLiveService,
        protected ProtectedMediaService $protectedMedia,
        protected LessonWatchProgressService $lessonWatchProgress,
        protected CourseCertificateIssuanceService $certificateIssuance,
        protected CourseFinalExamService $courseFinalExamService,
        protected SubscriptionAccessService $subscriptionAccess,
        protected VideoPlaybackTokenService $playbackTokens,
    ) {}

    public function index(Request $request)
    {
        $courses = $this->courseService->getCourses($request->all(), null, true);

        return Inertia::render('courses/index', compact('courses'));
    }

    public function intWatchHistory(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'lesson_id' => 'nullable|exists:section_lessons,id',
            'panel' => 'nullable|string|in:forum,review,resource,summery',
        ]);

        $course = \App\Models\Course\Course::findOrFail($validated['course_id']);

        if (!$course->isEnrollmentOpen() && !$course->canPreviewBeforeLaunch($user)) {
            $message = $course->launch_at
                ? 'This course launches on ' . $course->launch_at->timezone(config('app.timezone'))->format('M j, Y g:i A') . '.'
                : 'This course is coming soon.';

            return back()->with('error', $message);
        }

        $watchHistory = $this->sectionService->initWatchHistory($validated['course_id'], 'lesson', $user->id);

        $url = route('course.player', [
            'type' => 'lesson',
            'watch_history' => $watchHistory->id,
            'lesson_id' => $validated['lesson_id'] ?? $watchHistory->current_watching_id,
        ]);

        if (!empty($validated['panel'])) {
            $url .= '?panel=' . $validated['panel'];
        }

        return redirect($url);
    }

    public function course_player(Request $request, string $type, WatchHistory $watch_history, string $lesson_id)
    {
        try {
            $user = Auth::user();

            $watching_id = $lesson_id ?? $watch_history->current_watching_id;
            $watching_type = $type;

            $course = $this->courseService->getUserCourseById($watch_history->course_id, $user);

            if (!$this->subscriptionAccess->canAccessPlayer($user, $course)) {
                $message = $course->usesSubscriptionBilling()
                    ? 'Your access to this course has expired. Resubscribe to continue.'
                    : 'Your access to this course has expired.';

                return redirect()
                    ->route('courses.show', ['slug' => $course->slug, 'id' => $course->id])
                    ->with('error', $message);
            }

            $watch_history = $this->coursePlay->syncPassedQuizzes($watch_history, $user->id);

            $subscriptionAccess = $this->subscriptionAccess->toFrontendPayload($user, $course);

            if ($type === 'quiz' && !$this->courseCompletionGateService->canAccessQuiz($course, $user->id, $lesson_id, $watch_history)) {
                $errorMessage = $subscriptionAccess['mode'] === 'completed_only' && $course->usesSubscriptionBilling()
                    ? 'This quiz is locked. Resubscribe to continue learning.'
                    : ($subscriptionAccess['mode'] === 'completed_only'
                        ? 'This quiz is locked.'
                        : 'Complete and receive trainer approval on all assignments before taking quizzes.');

                return redirect()
                    ->route('student.course.show', ['id' => $course->id, 'tab' => $subscriptionAccess['mode'] === 'completed_only' ? 'quizzes' : 'assignments'])
                    ->with('error', $errorMessage);
            }

            if ($type === 'lesson' && !$this->courseCompletionGateService->canAccessLesson($course, $user->id, $lesson_id, $watch_history)) {
                $errorMessage = $subscriptionAccess['mode'] === 'completed_only' && $course->usesSubscriptionBilling()
                    ? 'This lesson is locked. Resubscribe to continue learning.'
                    : ($subscriptionAccess['mode'] === 'completed_only'
                        ? 'This lesson is locked.'
                        : 'Complete the previous lesson before continuing.');
                return redirect()
                    ->route('course.player', [
                        'type' => $watch_history->current_watching_type,
                        'watch_history' => $watch_history->id,
                        'lesson_id' => $watch_history->current_watching_id,
                    ])
                    ->with('error', $errorMessage);
            }

            $watching = $this->coursePlay->getWatchingLesson($lesson_id, $type);

            if (!$watching) {
                return redirect()
                    ->route('student.course.show', ['id' => $course->id, 'tab' => 'modules'])
                    ->with('error', 'Lesson not found.');
            }

            if ($type === 'lesson' && $watching instanceof SectionLesson) {
                $watching = $this->protectedMedia->protectLessonForPlayer($watching, $user);

                if (
                    in_array($watching->lesson_type, ['video', 'video_url'], true)
                    && $watching->stream_protected
                    && $this->protectedMedia->lessonVideoIsStreamable($watching)
                ) {
                    $playbackToken = $this->playbackTokens->issue($user->id, $watching->id);
                    $playback = $this->protectedMedia->createVideoPlaybackPayload($watching, $playbackToken);
                    $playback['playback_token'] = $playbackToken;
                    $watching->setAttribute('video_playback', $playback);
                }
            }

            $reviews = $this->reviewService->getReviews(['course_id' => $course->id, ...$request->all()], true);
            $userReview = $this->reviewService->userReview($course->id, $user->id);
            $totalReviews = $this->reviewService->totalReviews($course->id);
            $zoomConfig = $this->zoomLiveService->zoomConfig;

            $section = null;
            $totalContent = 0;

            foreach ($course->sections as $courseSection) {
                $totalContent += count($courseSection->section_lessons) + count($courseSection->section_quizzes);

                if ($watching_type === 'lesson') {
                    $containsItem = $courseSection->section_lessons->contains('id', (int) $watching_id);
                } else {
                    $containsItem = $courseSection->section_quizzes->contains('id', (int) $watching_id);
                }

                if ($containsItem) {
                    $section = $courseSection;
                }
            }

            $watchHistory = $this->coursePlay->watchHistory($course, $watching_id, $watching_type, $user->id);
            $completion = $this->coursePlay->calculateCompletion($course, $watchHistory);
            $courseGates = $this->courseCompletionGateService->getGateStatus($course, $user->id, $completion, $watchHistory);

            $lessonWatchProgress = null;
            if ($type === 'lesson' && $watching instanceof SectionLesson) {
                $lessonWatchProgress = $this->lessonWatchProgress->getLessonProgress($watchHistory, $watching->id);
            }

            return Inertia::render('course-player/index', [
                'type' => $type,
                'course' => $course,
                'section' => $section,
                'reviews' => $reviews,
                'watching' => $watching,
                'totalContent' => $totalContent,
                'watchHistory' => $watchHistory,
                'courseGates' => $courseGates,
                'lessonWatchProgress' => $lessonWatchProgress,
                'userReview' => $userReview,
                'totalReviews' => $totalReviews,
                'zoomConfig' => $zoomConfig,
                'subscriptionAccess' => $subscriptionAccess,
            ]);
        } catch (\Throwable $th) {
            return redirect()->route('category.courses', ['category' => 'all'])->with('error', $th->getMessage());
        }
    }

    public function record_watch_progress(Request $request, WatchHistory $watch_history)
    {
        $user = Auth::user();

        if ((int) $watch_history->user_id !== (int) $user->id) {
            abort(403);
        }

        $course = $watch_history->course()->firstOrFail();

        if (!$this->subscriptionAccess->canMarkProgress($user, $course)) {
            return response()->json(['message' => 'Your subscription is inactive. Resubscribe to track progress.'], 403);
        }

        $validated = $request->validate([
            'lesson_id' => 'required|integer',
            'current_time' => 'required|numeric|min:0',
            'duration' => 'required|numeric|min:0',
        ]);

        if ((string) $watch_history->current_watching_id !== (string) $validated['lesson_id']) {
            return response()->json(['message' => 'Can only track progress for the current lesson.'], 422);
        }

        $this->lessonWatchProgress->recordProgress(
            $watch_history,
            $validated['lesson_id'],
            (float) $validated['current_time'],
            (float) $validated['duration'],
        );

        return response()->json(['ok' => true]);
    }

    public function mark_complete(Request $request, WatchHistory $watch_history)
    {
        $user = Auth::user();

        if ((int) $watch_history->user_id !== (int) $user->id) {
            abort(403);
        }

        $course = $watch_history->course()->firstOrFail();

        if (!$this->subscriptionAccess->canMarkProgress($user, $course)) {
            return back()->with('error', 'Your subscription is inactive. Resubscribe to mark lessons complete.');
        }

        $request->validate([
            'item_id' => 'required',
            'item_type' => 'required|in:lesson,quiz',
        ]);

        if (
            (string) $watch_history->current_watching_id !== (string) $request->item_id
            || $watch_history->current_watching_type !== $request->item_type
        ) {
            return back()->with('error', 'Can only mark the lesson you are currently viewing as complete.');
        }

        if ($request->item_type === 'lesson') {
            $lesson = SectionLesson::find($request->item_id);

            if ($lesson && $lesson->requires_submission) {
                return back()->with('error', 'Submit your completed activity and wait for trainer approval before continuing.');
            }

            if ($lesson && $this->lessonWatchProgress->isVideoLesson($lesson)) {
                if ($this->lessonWatchProgress->isExternalVideoLesson($lesson)) {
                    $this->lessonWatchProgress->recordFullProgress($watch_history, $lesson->id);
                } elseif (!$this->lessonWatchProgress->hasWatchedFully($watch_history, $lesson)) {
                    return back()->with('error', 'Watch the entire video before marking this lesson complete.');
                }
            }
        }

        $this->coursePlay->markItemComplete($watch_history, $request->item_id, $request->item_type);

        return back();
    }

    public function finish_course(WatchHistory $watch_history)
    {
        $user = Auth::user();
        $course = $watch_history->course()->with(['sections.section_lessons', 'assignments'])->firstOrFail();

        if (!$this->subscriptionAccess->canFinishCourse($user, $course)) {
            return back()->with('error', 'Your subscription is inactive. Resubscribe to finish the course.');
        }

        $completion = $this->coursePlay->calculateCompletion($course, $watch_history);
        $gates = $this->courseCompletionGateService->getGateStatus($course, $user->id, $completion, $watch_history);

        if (!$gates['certificate_unlocked']) {
            return back()->with('error', 'Complete all required phases before finishing the course.');
        }

        $completedItems = json_decode($watch_history->completed_watching, true) ?: [];
        $lastItem = [
            'id' => $watch_history->current_watching_id,
            'type' => $watch_history->current_watching_type,
        ];

        $itemExists = false;
        foreach ($completedItems as $item) {
            if ((string) $item['id'] === (string) $lastItem['id'] && $item['type'] === $lastItem['type']) {
                $itemExists = true;
                break;
            }
        }

        if (!$itemExists) {
            $completedItems[] = $lastItem;
        }

        $watch_history->completed_watching = json_encode($this->cleanupCompletedItems($completedItems));
        $watch_history->completion_date = now();
        $watch_history->save();

        $this->certificateIssuance->issueForCourse($course, $user);
        $this->courseFinalExamService->ensureFinalExamEnrollment($course, $user);

        return back()->with('success', 'Course completed successfully. Your certificate has been issued.');
    }

    private function cleanupCompletedItems(array $completedItems): array
    {
        $cleaned = [];
        $seen = [];

        foreach ($completedItems as $item) {
            $normalizedItem = [
                'id' => (string) $item['id'],
                'type' => $item['type'],
            ];

            $key = $normalizedItem['id'] . '|' . $normalizedItem['type'];

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $cleaned[] = $normalizedItem;
            }
        }

        return $cleaned;
    }
}
