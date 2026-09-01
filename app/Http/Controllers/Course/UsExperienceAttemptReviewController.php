<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Requests\UsExperience\SaveUsExperienceAttemptFeedbackRequest;
use App\Models\Course\Course;
use App\Models\Course\UsExperienceAttempt;
use App\Models\Course\UsExperiencePlan;
use App\Services\Course\ProtectedMediaService;
use App\Services\UsExperience\UsExperienceAttemptService;
use App\Services\UsExperience\UsExperiencePlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UsExperienceAttemptReviewController extends Controller
{
    public function __construct(
        private UsExperiencePlanService $plans,
        private UsExperienceAttemptService $attempts,
        private ProtectedMediaService $protectedMedia,
    ) {}

    public function index(Request $request, Course $course, UsExperiencePlan $plan): Response
    {
        $this->assertPlan($course, $plan);

        return Inertia::render('dashboard/courses/us-experience/attempts', [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
            ],
            'plan' => $plan->toTrainerArray(),
            'attempts' => $this->attempts->paginateForTrainer($plan, $request->all()),
            'filters' => [
                'search' => (string) $request->input('search', ''),
            ],
        ]);
    }

    public function show(Course $course, UsExperiencePlan $plan, UsExperienceAttempt $attempt): Response
    {
        $this->assertAttempt($course, $plan, $attempt);
        $attempt->loadMissing(['user:id,name,email']);

        return Inertia::render('dashboard/courses/us-experience/attempt-review', [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
            ],
            'plan' => $plan->toTrainerArray(),
            'attempt' => $attempt->toTrainerArray(includeBreakdown: true),
        ]);
    }

    public function download(Course $course, UsExperiencePlan $plan, UsExperienceAttempt $attempt, string $file): SymfonyResponse
    {
        $this->assertAttempt($course, $plan, $attempt);

        if (! in_array($file, ['pdf', 'excel'], true)) {
            abort(404);
        }

        $url = $file === 'pdf' ? $attempt->takeoff_pdf_url : $attempt->boq_xlsx_url;
        $fallback = $file === 'pdf'
            ? ($attempt->takeoff_pdf_name ?: 'takeoff.pdf')
            : ($attempt->boq_xlsx_name ?: 'boq.xlsx');

        if (! filled($url)) {
            abort(404, 'This attempt has no '.$file.' file.');
        }

        return $this->protectedMedia->streamStoredFileDownload($url, $fallback);
    }

    public function feedback(
        SaveUsExperienceAttemptFeedbackRequest $request,
        Course $course,
        UsExperiencePlan $plan,
        UsExperienceAttempt $attempt,
    ): RedirectResponse {
        $this->assertAttempt($course, $plan, $attempt);
        $this->attempts->saveTrainerFeedback($attempt, $request->validated('trainer_feedback'));

        return back()->with('success', 'Feedback saved. The student will see it on this plan.');
    }

    private function assertPlan(Course $course, UsExperiencePlan $plan): void
    {
        $this->plans->authorizeCourseAccess($course, Auth::user());

        if ((int) $plan->course_id !== (int) $course->id) {
            abort(404);
        }
    }

    private function assertAttempt(Course $course, UsExperiencePlan $plan, UsExperienceAttempt $attempt): void
    {
        $this->assertPlan($course, $plan);

        if ((int) $attempt->us_experience_plan_id !== (int) $plan->id) {
            abort(404);
        }
    }
}
