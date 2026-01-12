<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLessonResourceRequest;
use App\Http\Requests\UpdateLessonResourceRequest;
use App\Models\ChunkedUpload;
use App\Models\Course\LessonResource;
use App\Services\Course\LessonResourceService;

class LessonResourceController extends Controller
{
    public function __construct(private LessonResourceService $service) {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLessonResourceRequest $request)
    {
        $this->service->resourceStore($request->validated());

        return back()->with('success', 'Resource created successfully');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLessonResourceRequest $request, string $id)
    {
        $lessonResource = LessonResource::findOrFail($id);

        $this->service->resourceUpdate($lessonResource, $request->validated());

        return back()->with('success', 'Resource updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $lessonResource = LessonResource::findOrFail($id);

        $this->service->resourceDelete($lessonResource);

        return back()->with('success', 'Resource deleted successfully');
    }

    /**
     * Download the specified resource.
     */
    public function download(string $id)
    {
        $lessonResource = LessonResource::findOrFail($id);
        $resourceUrl = $lessonResource->resource;
        $chunkedUpload = ChunkedUpload::where('file_url', $resourceUrl)->first();
        $mimeType = $chunkedUpload->mime_type;
        $extension = pathinfo($chunkedUpload->original_filename, PATHINFO_EXTENSION);
        $filename = $lessonResource->title ? $lessonResource->title . '.' . $extension : $chunkedUpload->filename;

        // Convert URL to relative path (remove domain and /storage prefix)
        $relativePath = str_replace([url('/storage/'), url('storage/')], '', $resourceUrl);

        // Get the full file path
        $filePath = storage_path('app/public/' . $relativePath);

        // Check if file exists
        if (!file_exists($filePath)) {
            abort(404, 'File not found');
        }

        return response()->streamDownload(
            function () use ($filePath) {
                $stream = fopen($filePath, 'r');
                fpassthru($stream);
                fclose($stream);
            },
            $filename,
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]
        );
    }
}
