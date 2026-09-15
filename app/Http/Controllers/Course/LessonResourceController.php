<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLessonResourceRequest;
use App\Http\Requests\UpdateLessonResourceRequest;
use App\Models\Course\LessonResource;
use App\Services\Course\LessonResourceService;
use App\Services\Course\ProtectedMediaService;
use Illuminate\Http\Request;
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

    public function view(Request $request, string $resource)
    {
        $lessonResource = LessonResource::findOrFail($resource);
        $this->protectedMedia->authorizeResourceAccess(Auth::user(), $lessonResource);

        if ($lessonResource->type === 'link') {
            return redirect()->away($lessonResource->resource);
        }

        if (! $this->protectedMedia->resourceIsStreamable($lessonResource)) {
            abort(404, 'File not found');
        }

        $mimeType = $this->protectedMedia->resolveMimeType($lessonResource->resource);

        return $this->protectedMedia->streamMediaResponse($request, $lessonResource->resource, $mimeType);
    }

    public function download(string $id)
    {
        $lessonResource = LessonResource::findOrFail($id);
        $this->protectedMedia->authorizeResourceDownload(Auth::user(), $lessonResource);

        if ($lessonResource->type === 'link') {
            return redirect()->away($lessonResource->resource);
        }

        if (! $this->protectedMedia->resourceIsStreamable($lessonResource)) {
            abort(404, 'File not found');
        }

        $filename = trim((string) $lessonResource->title) !== ''
            ? $lessonResource->title
            : 'resource';

        return $this->protectedMedia->streamStoredFileDownload($lessonResource->resource, $filename);
    }
}
