<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Requests\UsExperience\SubmitUsExperienceAttemptRequest;
use App\Models\Course\Course;
use App\Models\Course\UsExperiencePlan;
use App\Services\Payment\SubscriptionAccessService;
use App\Services\StudentService;
use App\Services\UsExperience\UsExperienceAttemptService;
use App\Services\UsExperience\UsExperiencePlanService;
use App\Services\UsExperience\UsExperienceUnlockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UsExperienceStudentController extends Controller
{
    public function __construct(
        private StudentService $studentService,
        private SubscriptionAccessService $subscriptionAccess,
        private UsExperienceUnlockService $unlock,
        private UsExperiencePlanService $plans,
        private UsExperienceAttemptService $attempts,
    ) {}

    public function downloadPack(UsExperiencePlan $plan): BinaryFileResponse|RedirectResponse
    {
        $user = Auth::user();
        $course = $this->enrolledCourse($plan);
        $canUseFiles = $this->subscriptionAccess->getAccessMode($user, $course) === 'full';
        $ordered = $this->unlock->orderedReadyPlans($course);
        $this->unlock->assertCanDownload($plan, $ordered, $user, $canUseFiles);

        try {
            $pack = $this->plans->buildStudentPack($plan);
        } catch (\InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return response()
            ->download($pack['path'], $pack['name'])
            ->deleteFileAfterSend(true);
    }

    public function submit(SubmitUsExperienceAttemptRequest $request, UsExperiencePlan $plan): RedirectResponse
    {
        $user = Auth::user();
        $course = $this->enrolledCourse($plan);
        $canUseFiles = $this->subscriptionAccess->getAccessMode($user, $course) === 'full';
        $ordered = $this->unlock->orderedReadyPlans($course);
        $this->unlock->assertCanSubmit($plan, $ordered, $user, $canUseFiles);

        try {
            $attempt = $this->attempts->submit(
                $plan,
                $user,
                $request->validated('takeoff_pdf_url'),
                $request->validated('takeoff_pdf_name'),
                $request->validated('boq_xlsx_url'),
                $request->validated('boq_xlsx_name'),
            );
        } catch (\InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $message = $attempt->isPassed()
            ? 'Plan passed at '.$attempt->lines_percent.'%. The next plan is unlocked.'
            : 'Submitted. Accuracy '.$attempt->lines_percent.'% (pass mark '.$plan->pass_mark.'%). Try again if you have attempts left.';

        return back()->with($attempt->isPassed() ? 'success' : 'info', $message);
    }

    private function enrolledCourse(UsExperiencePlan $plan): Course
    {
        try {
            return $this->studentService->getEnrolledCourse((string) $plan->course_id, Auth::user());
        } catch (\Exception $exception) {
            abort(403, $exception->getMessage());
        }
    }
}
