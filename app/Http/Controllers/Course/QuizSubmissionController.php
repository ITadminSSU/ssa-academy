<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuizSubmissionRequest;
use App\Models\Course\SectionQuiz;
use App\Models\User;
use App\Services\Course\CourseCompletionGateService;
use App\Services\Course\SectionQuizService;
use App\Services\Payment\SubscriptionAccessService;

class QuizSubmissionController extends Controller
{
    public function __construct(
        private SectionQuizService $quizService,
        private CourseCompletionGateService $courseCompletionGateService,
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
            return back()->with('error', 'Your subscription is inactive. Resubscribe to take quizzes.');
        }

        if (!$this->courseCompletionGateService->canAccessQuiz($quiz->course, $request->user_id, $quiz->id)) {
            return back()->with('error', 'Submit all course assignments before taking the quiz.');
        }

        $submission = $this->quizService->quizSubmission($request->validated());

        if (!$submission) {
            return back()->with('error', 'You have done your retake attempts');
        }

        return back()->with('success', 'Quiz submitted successfully');
    }
}
