<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Requests\UsExperience\SaveUsExperienceTolerancesRequest;
use App\Http\Requests\UsExperience\SaveUsExperienceUploadedFileRequest;
use App\Http\Requests\UsExperience\StoreUsExperiencePlanRequest;
use App\Http\Requests\UsExperience\UpdateUsExperiencePlanRequest;
use App\Models\Course\Course;
use App\Models\Course\UsExperiencePlan;
use App\Services\UsExperience\UsExperiencePlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UsExperiencePlanController extends Controller
{
    public function __construct(
        private UsExperiencePlanService $plans,
    ) {}

    public function store(StoreUsExperiencePlanRequest $request, Course $course): RedirectResponse
    {
        $this->plans->authorizeCourseAccess($course, Auth::user());
        $plan = $this->plans->create($course, $request->validated());

        return redirect()
            ->route('courses.edit', ['course' => $course->id, 'tab' => 'us-experience', 'plan' => $plan->id])
            ->with('success', 'Plan created. Upload drawings, the answer key, and the blank student template.');
    }

    public function update(UpdateUsExperiencePlanRequest $request, Course $course, UsExperiencePlan $plan): RedirectResponse
    {
        $this->assertPlan($course, $plan);
        $this->plans->update($plan, $request->validated());

        return back()->with('success', 'Plan settings saved.');
    }

    public function destroy(Course $course, UsExperiencePlan $plan): RedirectResponse
    {
        $this->assertPlan($course, $plan);
        $this->plans->delete($plan);

        return redirect()
            ->route('courses.edit', ['course' => $course->id, 'tab' => 'us-experience'])
            ->with('success', 'Plan deleted.');
    }

    public function move(Request $request, Course $course, UsExperiencePlan $plan): RedirectResponse
    {
        $this->assertPlan($course, $plan);
        $direction = $request->validate([
            'direction' => 'required|in:up,down',
        ])['direction'];
        $this->plans->move($plan, $direction);

        return back()->with('success', 'Plan order updated.');
    }

    public function addDrawing(SaveUsExperienceUploadedFileRequest $request, Course $course, UsExperiencePlan $plan): RedirectResponse
    {
        $this->assertPlan($course, $plan);
        $this->plans->addDrawing($plan, $request->validated('file_url'), $request->validated('file_name'));

        return back()->with('success', 'Reference drawing added.');
    }

    public function removeDrawing(Request $request, Course $course, UsExperiencePlan $plan): RedirectResponse
    {
        $this->assertPlan($course, $plan);
        $fileUrl = $request->validate([
            'file_url' => 'required|string|max:2048',
        ])['file_url'];
        $this->plans->removeDrawing($plan, $fileUrl);

        return back()->with('success', 'Reference drawing removed.');
    }

    public function importAnswerKey(SaveUsExperienceUploadedFileRequest $request, Course $course, UsExperiencePlan $plan): RedirectResponse
    {
        $this->assertPlan($course, $plan);

        try {
            $result = $this->plans->importAnswerKey(
                $plan,
                $request->validated('file_url'),
                $request->validated('file_name'),
            );
        } catch (\InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with(
            'success',
            'Answer key validated and imported. '.$result['line_count'].' quantity line(s) are ready. Default tolerance is 2%; edit any line below if needed.'
        );
    }

    public function saveStudentTemplate(SaveUsExperienceUploadedFileRequest $request, Course $course, UsExperiencePlan $plan): RedirectResponse
    {
        $this->assertPlan($course, $plan);
        $this->plans->saveStudentTemplate($plan, $request->validated('file_url'), $request->validated('file_name'));

        return back()->with('success', 'Blank student template saved.');
    }

    public function saveTutorial(SaveUsExperienceUploadedFileRequest $request, Course $course, UsExperiencePlan $plan): RedirectResponse
    {
        $this->assertPlan($course, $plan);
        $this->plans->saveTutorialVideo($plan, $request->validated('file_url'), $request->validated('file_name'));

        return back()->with('success', 'Tutorial video saved. Students see it after they submit.');
    }

    public function saveTolerances(SaveUsExperienceTolerancesRequest $request, Course $course, UsExperiencePlan $plan): RedirectResponse
    {
        $this->assertPlan($course, $plan);
        $this->plans->saveLineTolerances($plan, $request->validated('tolerances'));

        return back()->with('success', 'Per-line tolerances saved.');
    }

    private function assertPlan(Course $course, UsExperiencePlan $plan): void
    {
        $this->plans->authorizeCourseAccess($course, Auth::user());

        if ((int) $plan->course_id !== (int) $course->id) {
            abort(404);
        }
    }
}
