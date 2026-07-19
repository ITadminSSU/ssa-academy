<?php

namespace App\Http\Controllers;

use App\Models\LearnerResource;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ResourceController extends Controller
{
    public const TYPES = ['course_outline', 'estimating_template', 'sample_project'];

    public function __construct(protected MediaService $mediaService) {}

    public function index()
    {
        return Inertia::render('dashboard/resources/index', [
            'resources' => LearnerResource::orderByDesc('created_at')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateResource($request);

        $resource = LearnerResource::create([
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'link' => $data['link'] ?? null,
        ]);

        if ($request->hasFile('file')) {
            $this->attachFile($resource, $request->file('file'));
        }

        return redirect()->back()->with('success', 'Resource created');
    }

    public function update(Request $request, LearnerResource $resource)
    {
        $data = $this->validateResource($request);

        $resource->update([
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'link' => $data['link'] ?? null,
        ]);

        if ($request->hasFile('file')) {
            $this->attachFile($resource, $request->file('file'));
        }

        return redirect()->back()->with('success', 'Resource updated');
    }

    public function destroy(LearnerResource $resource)
    {
        $resource->delete();

        return redirect()->back()->with('success', 'Resource deleted');
    }

    private function validateResource(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'in:' . implode(',', self::TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'link' => ['nullable', 'string', 'max:2048'],
            'file' => ['nullable', 'file', 'max:51200'],
        ]);
    }

    private function attachFile(LearnerResource $resource, $file): void
    {
        $url = $this->mediaService->addNewDeletePrev($resource, $file, 'file');

        $resource->update([
            'file' => $url,
            'file_name' => $file->getClientOriginalName(),
        ]);
    }
}
