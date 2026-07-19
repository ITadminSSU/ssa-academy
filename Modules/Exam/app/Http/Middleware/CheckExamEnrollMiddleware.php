<?php

namespace Modules\Exam\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Course\CourseFinalExamService;
use App\Services\Payment\SubscriptionAccessService;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamEnrollment;

class CheckExamEnrollMiddleware
{
    public function __construct(
        private CourseFinalExamService $courseFinalExamService,
        private SubscriptionAccessService $subscriptionAccess,
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user->role == 'admin') {
            return $next($request);
        }

        $exam = Exam::find($request->exam_id);

        if (!$exam) {
            return back()->with('error', 'Exam not found');
        }

        if ($user->role == 'instructor' && $user->instructor_id == $exam->instructor_id) {
            return $next($request);
        }

        $enrollment = ExamEnrollment::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->first();

        if ($enrollment) {
            if (!$this->subscriptionAccess->hasActiveSubscriptionForLinkedExam($user, (int) $exam->id)) {
                return back()->with('error', 'An active course subscription is required to access this exam.');
            }

            return $next($request);
        }

        if ($this->courseFinalExamService->userCompletedLinkedCourse((int) $exam->id, (int) $user->id)) {
            if (!$this->subscriptionAccess->hasActiveSubscriptionForLinkedExam($user, (int) $exam->id)) {
                return back()->with('error', 'An active course subscription is required to access this exam.');
            }

            $this->courseFinalExamService->ensureEnrollmentForExam((int) $exam->id, $user);

            return $next($request);
        }

        return back()->with('error', 'You are not enrolled in this exam');
    }
}
