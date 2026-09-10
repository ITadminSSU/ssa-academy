<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuizSubmissionRequest;
use App\Http\Requests\StoreQuizTakeoffSubmissionRequest;
use App\Models\Course\SectionQuiz;
use App\Models\Course\WatchHistory;
use App\Models\User;
use App\Services\Course\CourseCompletionGateService;
use App\Services\Course\CoursePlayerService;
use App\Services\Course\QuizTakeoffService;
use App\Services\Course\SectionQuizService;
use App\Services\Payment\SubscriptionAccessService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuizSubmissionController extends Controller
{
    public function __construct(
        private SectionQuizService $quizService,
        private CourseCompletionGateService $courseCompletionGateService,
        private CoursePlayerService $coursePlayerService,
        private SubscriptionAccessService $subscriptionAccess,
        private QuizTakeoffService $quizTakeoff,
    ) {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreQuizSubmissionRequest $request)
    {
        $quiz = SectionQuiz::with('course.assignments')->findOrFail($request->section_quiz_id);
        $user = User::query()->findOrFail($request->user_id);

        if (!$this->subscriptionAccess->canMarkProgress($user, $quiz->course)) {
            $message = $quiz->course->usesSubscriptionBilling()
                ? 'Your subscription is inactive. Resubscribe to take quizzes.'
                : 'Your access to this course is inactive.';

            return back()->with('error', $message);
        }

        if (!$this->courseCompletionGateService->canAccessQuiz($quiz->course, $request->user_id, $quiz->id)) {
            return back()->with('error', 'Complete all video lessons before taking the quiz.');
        }

        $submission = $this->quizService->quizSubmission($request->validated());

        if (!$submission) {
            return back()->with('error', 'You have done your retake attempts');
        }

        if ($submission->is_passed) {
            $watchHistory = WatchHistory::query()
                ->where('user_id', $user->id)
                ->where('course_id', $quiz->course_id)
                ->first();

            if ($watchHistory) {
                $this->coursePlayerService->markItemComplete($watchHistory, $quiz->id, 'quiz');
            }
        }

        return back()->with('success', 'Quiz submitted successfully');
    }

    public function submitTakeoff(StoreQuizTakeoffSubmissionRequest $request): RedirectResponse
    {
        $quiz = SectionQuiz::with(['course', 'quiz_questions'])->findOrFail($request->section_quiz_id);
        $user = User::query()->findOrFail($request->user_id);

        if (!$this->subscriptionAccess->canMarkProgress($user, $quiz->course)) {
            $message = $quiz->course->usesSubscriptionBilling()
                ? 'Your subscription is inactive. Resubscribe to take quizzes.'
                : 'Your access to this course is inactive.';

            return back()->with('error', $message);
        }

        if (!$this->courseCompletionGateService->canAccessQuiz($quiz->course, $user->id, $quiz->id)) {
            return back()->with('error', 'Complete the previous item before taking this quiz.');
        }

        try {
            $submission = $this->quizTakeoff->submit($quiz, $user, $request->validated());
        } catch (\InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if (!$submission) {
            return back()->with('error', 'You have done your retake attempts');
        }

        if ($submission->is_passed) {
            $watchHistory = WatchHistory::query()
                ->where('user_id', $user->id)
                ->where('course_id', $quiz->course_id)
                ->first();

            if ($watchHistory) {
                $this->coursePlayerService->markItemComplete($watchHistory, $quiz->id, 'quiz');
            }
        }

        $message = $submission->is_passed
            ? 'Takeoff passed at '.$submission->total_marks.' marks.'
            : 'Takeoff submitted. Score '.$submission->total_marks.' (pass mark '.$quiz->pass_mark.'). Try again if you have attempts left.';

        return back()->with($submission->is_passed ? 'success' : 'info', $message);
    }

    public function downloadTakeoffPack(SectionQuiz $quiz): BinaryFileResponse|RedirectResponse
    {
        $user = request()->user();
        $quiz->loadMissing(['course', 'quiz_questions']);

        if (!$this->subscriptionAccess->canAccessPlayer($user, $quiz->course)) {
            abort(403);
        }

        if (!$this->courseCompletionGateService->canAccessQuiz($quiz->course, $user->id, $quiz->id)) {
            return back()->with('error', 'Complete the previous item before downloading this takeoff.');
        }

        $question = $this->quizTakeoff->takeoffQuestion($quiz);

        if (! $question) {
            return back()->with('error', 'This quiz is not a quantity takeoff.');
        }

        try {
            $pack = $this->quizTakeoff->buildStudentPack($question);
        } catch (\InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return response()
            ->download($pack['path'], $pack['name'])
            ->deleteFileAfterSend(true);
    }
}
