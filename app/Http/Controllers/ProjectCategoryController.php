<?php

namespace App\Http\Controllers;

use App\Models\ProjectCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProjectCategoryController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        ProjectCategory::create([
            'title' => $data['title'],
            'slug' => Str::slug($data['title']) . '-' . Str::random(5),
        ]);

        return redirect()->back()->with('success', 'Project category created');
    }

    public function update(Request $request, ProjectCategory $project_category)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $project_category->update([
            'title' => $data['title'],
            'slug' => Str::slug($data['title']) . '-' . $project_category->id,
        ]);

        return redirect()->back()->with('success', 'Project category updated');
    }

    public function destroy(ProjectCategory $project_category)
    {
        $project_category->delete();

        return redirect()->back()->with('success', 'Project category deleted');
    }
}
