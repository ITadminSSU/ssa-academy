<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectSubmission;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectLibraryController extends Controller
{
    public function __construct(protected MediaService $mediaService) {}

    public function index()
    {
        return Inertia::render('dashboard/projects/index', [
            'projects' => Project::with('category')->orderByDesc('created_at')->get(),
            'projectCategories' => ProjectCategory::withCount('projects')->orderBy('title')->get(),
            'submissions' => ProjectSubmission::with([
                'project:id,title,project_category_id',
                'project.category:id,title',
                'user:id,name,email',
                'scorer:id,name',
            ])->orderByDesc('submitted_at')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'project_category_id' => ['nullable', 'exists:project_categories,id'],
            'is_completed' => ['boolean'],
            'is_published' => ['boolean'],
            'file' => ['nullable', 'file', 'max:51200'],
        ]);

        $project = Project::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'project_category_id' => $data['project_category_id'] ?? null,
            'is_completed' => $data['is_completed'] ?? false,
            'is_published' => $data['is_published'] ?? false,
        ]);

        if ($request->hasFile('file')) {
            $this->attachFile($project, $request->file('file'));
        }

        return redirect()->back()->with('success', 'Project created');
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'project_category_id' => ['nullable', 'exists:project_categories,id'],
            'is_completed' => ['boolean'],
            'is_published' => ['boolean'],
            'file' => ['nullable', 'file', 'max:51200'],
        ]);

        $project->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'project_category_id' => $data['project_category_id'] ?? null,
            'is_completed' => $data['is_completed'] ?? false,
            'is_published' => $data['is_published'] ?? false,
        ]);

        if ($request->hasFile('file')) {
            $this->attachFile($project, $request->file('file'));
        }

        return redirect()->back()->with('success', 'Project updated');
    }

    public function togglePublish(Project $project)
    {
        $project->update(['is_published' => !$project->is_published]);

        return redirect()->back()->with('success', $project->is_published ? 'Project published' : 'Project unpublished');
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()->back()->with('success', 'Project deleted');
    }

    private function attachFile(Project $project, $file): void
    {
        $url = $this->mediaService->addNewDeletePrev($project, $file, 'file');

        $project->update([
            'file' => $url,
            'file_name' => $file->getClientOriginalName(),
        ]);
    }
}
