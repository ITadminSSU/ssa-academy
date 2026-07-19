<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\Course\LessonResource;
use App\Models\Course\SectionLesson;
use App\Services\Course\ProtectedMediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProtectedMediaController extends Controller
{
    public function __construct(private ProtectedMediaService $protectedMedia) {}

    public function streamLesson(Request $request, SectionLesson $lesson)
    {
        $user = Auth::user();
        $this->protectedMedia->authorizeLessonAccess($user, $lesson);

        $originalSrc = $lesson->getRawOriginal('lesson_src') ?: $lesson->lesson_src;
        $filePath = $this->protectedMedia->resolveLocalPath($originalSrc);

        if (!$filePath || !is_file($filePath)) {
            abort(404, 'Media file not found.');
        }

        $mimeType = $this->protectedMedia->resolveMimeType($originalSrc);

        return $this->protectedMedia->streamFileResponse($request, $filePath, $mimeType);
    }

    public function viewResource(Request $request, LessonResource $resource)
    {
        $user = Auth::user();
        $this->protectedMedia->authorizeResourceAccess($user, $resource);

        if ($resource->type === 'link') {
            return redirect()->away($resource->resource);
        }

        $filePath = $this->protectedMedia->resolveResourcePath($resource);

        if (!$filePath || !is_file($filePath)) {
            abort(404, 'Resource file not found.');
        }

        $mimeType = $this->protectedMedia->resolveMimeType($resource->resource);

        return $this->protectedMedia->streamFileResponse($request, $filePath, $mimeType);
    }
}
