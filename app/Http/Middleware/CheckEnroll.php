<?php

namespace App\Http\Middleware;

use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\Course\WatchHistory;
use App\Models\User;
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

        if ($user && $this->shouldHoldForAgreement($request, $user)) {
            $courseId = $this->courseIdFromRequest($request);

            if ($courseId) {
                $request->session()->put('pending_course_start_id', (int) $courseId);
            }

            return redirect()
                ->route('legal.agreement.show')
                ->with('info', 'Sign the student agreement before starting this course.');
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

            if (! $watchHistory) {
                return back()->with('error', 'Invalid watch history');
            }

            $courseId = $watchHistory->course_id;
        } else {
            $courseId = $request->input('course_id');
        }

        $course = $courseId ? Course::find($courseId) : null;

        if (! $course) {
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

        $message = 'You are not enrolled in this course';

        if ($enrollment?->isReservedSeat()) {
            $message = 'Your seat is reserved. Pay the remaining balance on the course page to unlock full access.';
        } elseif ($course->isComingSoon()) {
            $message = $course->launch_at
                ? 'This course launches on '.$course->launch_at->timezone(config('app.timezone'))->format('M j, Y g:i A').'.'
                : 'This course is coming soon.';
        } elseif ($enrollment && $course->usesInstallmentSubscription()) {
            $message = 'Your installment payment is past due. Pay the open invoice to unlock this course.';
        } elseif ($enrollment) {
            $message = $course->usesSubscriptionBilling()
                ? 'Your access to this course has expired. Resubscribe to continue.'
                : 'Your access to this course has expired.';
        }

        return redirect()
            ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
            ->with('error', $message);
    }

    /**
     * Ask for the agreement only when a student is starting a course they have not opened yet.
     */
    private function shouldHoldForAgreement(Request $request, User $user): bool
    {
        if ($user->role !== 'student' || ! $this->legalAgreement->requiresAcceptance($user)) {
            return false;
        }

        return ! $this->courseAlreadyStarted($request, $user);
    }

    private function courseAlreadyStarted(Request $request, User $user): bool
    {
        $routeWatchHistory = $request->route('watch_history');

        if ($routeWatchHistory instanceof WatchHistory) {
            return (int) $routeWatchHistory->user_id === (int) $user->id;
        }

        if ($routeWatchHistory) {
            $history = WatchHistory::find($routeWatchHistory);

            return $history !== null && (int) $history->user_id === (int) $user->id;
        }

        $courseId = $request->input('course_id');

        if (! $courseId) {
            return false;
        }

        return WatchHistory::query()
            ->where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->exists();
    }

    private function courseIdFromRequest(Request $request): ?int
    {
        $routeWatchHistory = $request->route('watch_history');

        if ($routeWatchHistory instanceof WatchHistory) {
            return (int) $routeWatchHistory->course_id;
        }

        if ($routeWatchHistory) {
            $history = WatchHistory::find($routeWatchHistory);

            return $history ? (int) $history->course_id : null;
        }

        $courseId = $request->input('course_id');

        return $courseId ? (int) $courseId : null;
    }
}
