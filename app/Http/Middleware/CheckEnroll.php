<?php

namespace App\Http\Middleware;

use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\Course\WatchHistory;
use App\Services\LegalAgreementService;
use App\Services\Payment\SubscriptionAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckEnroll
{
    public function __construct(
        private LegalAgreementService $legalAgreement,
        private SubscriptionAccessService $subscriptionAccess,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($this->legalAgreement->requiresAcceptance($user)) {
            return redirect()->route('legal.agreement.show');
        }

        if ($user->role == 'admin') {
            return $next($request);
        }

        // Most player routes carry a {watch_history} route param, but the
        // "init watch history" route instead posts a course_id. Resolve the
        // target course id from whichever is present.
        $courseId = null;
        $routeWatchHistory = $request->route('watch_history');

        if ($routeWatchHistory) {
            $watchHistory = $routeWatchHistory instanceof WatchHistory
                ? $routeWatchHistory
                : WatchHistory::find($routeWatchHistory);

            if (!$watchHistory) {
                return back()->with('error', 'Invalid watch history');
            }

            $courseId = $watchHistory->course_id;
        } else {
            $courseId = $request->input('course_id');
        }

        $course = $courseId ? Course::find($courseId) : null;

        if (!$course) {
            return back()->with('error', 'Invalid course');
        }

        if ($user->role == 'instructor' && $user->instructor_id == $course->instructor_id) {
            return $next($request);
        }

        $enrollment = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->with('subscription')
            ->first();

        if ($enrollment && $this->subscriptionAccess->canAccessPlayer($user, $course, $enrollment)) {
            return $next($request);
        }

        if ($enrollment) {
            return back()->with('error', 'Your access to this course has expired. Resubscribe to continue.');
        }

        return back()->with('error', 'You are not enrolled in this course');
    }
}
