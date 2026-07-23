<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuizSubmissionRequest;
use App\Models\Course\SectionQuiz;
use App\Models\Course\WatchHistory;
use App\Models\User;
use App\Services\Course\CourseCompletionGateService;
use App\Services\Course\CoursePlayerService;
use App\Services\Course\SectionQuizService;
use App\Services\Payment\SubscriptionAccessService;

class QuizSubmissionController extends Controller
{
    public function __construct(
        private SectionQuizService $quizService,
        private CourseCompletionGateService $courseCompletionGateService,
        private CoursePlayerService $coursePlayerService,
        private SubscriptionAccessService $subscriptionAccess,
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
            return back()->with('error', 'Submit all course assignments before taking the quiz.');
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
}
