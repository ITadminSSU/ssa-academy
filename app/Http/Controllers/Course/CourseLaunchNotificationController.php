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
}
