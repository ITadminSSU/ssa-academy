<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course\Course;
use App\Models\Setting;
use App\Services\SettingsService;
use App\Support\PefPackageSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EnrollmentPackageController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    public function pef(): Response
    {
        $system = $this->system();
        $fields = is_array($system->fields) ? $system->fields : [];

        return Inertia::render('dashboard/enrollments/pef-package', [
            'pef' => [
                'enabled' => filter_var($fields['pef_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'access_duration' => (string) ($fields['pef_access_duration'] ?? PefPackageSettings::DEFAULT_DURATION),
                'course_ids' => array_map('strval', is_array($fields['pef_course_ids'] ?? null) ? $fields['pef_course_ids'] : []),
            ],
            'courses' => $this->courseOptions($fields['pef_course_ids'] ?? []),
            'durations' => PefPackageSettings::DURATIONS,
        ]);
    }

    public function updatePef(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pef_enabled' => ['required', 'boolean'],
            'pef_access_duration' => ['required', 'string'],
            'pef_course_ids' => ['present', 'array'],
            'pef_course_ids.*' => ['integer'],
        ]);

        $this->settings->updateEnrollmentSettings($validated);

        return back()->with('success', 'PEF package updated.');
    }

    public function companion(): Response
    {
        $system = $this->system();
        $fields = is_array($system->fields) ? $system->fields : [];
        $courseId = $fields['companion_course_id'] ?? null;

        return Inertia::render('dashboard/enrollments/companion-course', [
            'companion' => [
                'enabled' => filter_var($fields['companion_auto_enroll_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'course_id' => $courseId ? (string) $courseId : '',
            ],
            'courses' => $this->courseOptions($courseId ? [$courseId] : []),
        ]);
    }

    public function updateCompanion(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'companion_auto_enroll_enabled' => ['required', 'boolean'],
            'companion_course_id' => ['nullable', 'integer'],
        ]);

        $this->settings->updateEnrollmentSettings($validated);

        return back()->with('success', 'Companion course updated.');
    }

    private function system(): Setting
    {
        $system = $this->settings->getSetting(['type' => 'system']);

        abort_if($system === null, 404);

        return $system;
    }

    /**
     * @param  mixed  $selectedIds
     * @return list<array{id: int, title: string}>
     */
    private function courseOptions(mixed $selectedIds): array
    {
        $selectedIds = is_array($selectedIds) ? $selectedIds : [];

        return Course::query()
            ->select(['id', 'title', 'status'])
            ->where('status', 'approved')
            ->when($selectedIds !== [], function ($query) use ($selectedIds) {
                $query->orWhereIn('id', $selectedIds);
            })
            ->orderBy('title')
            ->get()
            ->map(fn (Course $course) => [
                'id' => $course->id,
                'title' => $course->title,
            ])
            ->all();
    }
}
