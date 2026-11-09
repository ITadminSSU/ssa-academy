<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentLabelRequest;
use App\Http\Requests\UpdateStudentLabelRequest;
use App\Models\StudentLabel;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StudentLabelController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('dashboard/enrollments/student-labels', [
            'labels' => StudentLabel::query()->orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    public function store(StoreStudentLabelRequest $request): RedirectResponse
    {
        StudentLabel::query()->create($request->validated());

        return back()->with('success', 'Student label created.');
    }

    public function update(UpdateStudentLabelRequest $request, StudentLabel $studentLabel): RedirectResponse
    {
        $studentLabel->update($request->validated());

        return back()->with('success', 'Student label updated.');
    }

    public function destroy(StudentLabel $studentLabel): RedirectResponse
    {
        $studentLabel->delete();

        return back()->with('success', 'Student label deleted. Students who had it no longer show that pill.');
    }
}
