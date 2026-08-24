<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseLaunchNotificationRequest;
use App\Models\Course\Course;
use App\Services\Course\CourseLaunchNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class CourseLaunchNotificationController extends Controller
{
    public function __construct(
        private CourseLaunchNotificationService $launchNotifications,
    ) {}

    public function store(StoreCourseLaunchNotificationRequest $request, Course $course): RedirectResponse
    {
        $this->launchNotifications->subscribe(
            $course,
            $request->validated('email'),
            Auth::user(),
        );

        return back()->with('success', __('frontend.notify_subscribed'));
    }

    /**
     * Trainer/admin: open the course if still Coming Soon, then email the waitlist.
     */
    public function send(Course $course): RedirectResponse
    {
        $this->authorizeCourseNotify($course);

        $result = $this->launchNotifications->openAndNotify($course);

        if ($result['pending_before'] === 0) {
            return back()->with(
                'success',
                $result['opened']
                    ? 'Course is open now. There was no one on the notify list.'
                    : 'There was no one waiting on the notify list.',
            );
        }

        if ($result['sent'] === 0) {
            $detail = $result['last_error']
                ? ' Details: '.$result['last_error']
                : ' Check SMTP / Resend settings and the application log.';

            return back()->with(
                'error',
                'Could not send launch emails.'.$detail,
            );
        }

        $openedNote = $result['opened'] ? 'Course is open now. ' : '';
        $failNote = $result['failed'] > 0
            ? ' '.$result['failed'].' failed'.($result['last_error'] ? ' ('.$result['last_error'].')' : '').'.'
            : '';

        return back()->with(
            'success',
            $openedNote.'Sent '.$result['sent'].' launch notification email(s).'.$failNote,
        );
    }

    private function authorizeCourseNotify(Course $course): void
    {
        if (isAdmin()) {
            return;
        }

        $user = Auth::user();

        if ($user && (int) $user->instructor_id === (int) $course->instructor_id) {
            return;
        }

        abort(403);
    }
}
