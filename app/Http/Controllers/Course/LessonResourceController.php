<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLessonResourceRequest;
use App\Http\Requests\UpdateLessonResourceRequest;
use App\Models\ChunkedUpload;
use App\Models\Course\LessonResource;
use App\Services\Course\LessonResourceService;
use App\Services\Course\ProtectedMediaService;
use Illuminate\Support\Facades\Auth;

class LessonResourceController extends Controller
{
    public function __construct(
        private LessonResourceService $service,
        private ProtectedMediaService $protectedMedia,
    ) {}

    public function store(StoreLessonResourceRequest $request)
    {
        $this->service->resourceStore($request->validated());

        return back()->with('success', 'Resource created successfully');
    }

    public function update(UpdateLessonResourceRequest $request, string $id)
    {
        $lessonResource = LessonResource::findOrFail($id);

        $this->service->resourceUpdate($lessonResource, $request->validated());

        return back()->with('success', 'Resource updated successfully');
    }

    public function destroy(string $id)
    {
        $lessonResource = LessonResource::findOrFail($id);

        $this->service->resourceDelete($lessonResource);

        return back()->with('success', 'Resource deleted successfully');
    }

    public function view(string $id)
    {
        $lessonResource = LessonResource::findOrFail($id);
        $this->protectedMedia->authorizeResourceAccess(Auth::user(), $lessonResource);

        if ($lessonResource->type === 'link') {
            return redirect()->away($lessonResource->resource);
        }

        $filePath = $this->protectedMedia->resolveResourcePath($lessonResource);

        if (!$filePath || !is_file($filePath)) {
            abort(404, 'File not found');
        }

        $mimeType = $this->protectedMedia->resolveMimeType($lessonResource->resource);

        return response()->file($filePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function download(string $id)
    {
        $lessonResource = LessonResource::findOrFail($id);
        $this->protectedMedia->authorizeResourceDownload(Auth::user(), $lessonResource);

        $resourceUrl = $lessonResource->resource;
        $chunkedUpload = ChunkedUpload::where('file_url', $resourceUrl)->first();
        $mimeType = $chunkedUpload?->mime_type ?? $this->protectedMedia->resolveMimeType($resourceUrl);
        $extension = $chunkedUpload
            ? pathinfo($chunkedUpload->original_filename, PATHINFO_EXTENSION)
            : pathinfo($resourceUrl, PATHINFO_EXTENSION);
        $filename = $lessonResource->title
            ? $lessonResource->title . ($extension ? '.' . $extension : '')
            : ($chunkedUpload?->filename ?? 'resource');

        $filePath = $this->protectedMedia->resolveResourcePath($lessonResource);

        if (!$filePath || !is_file($filePath)) {
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
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }
}
