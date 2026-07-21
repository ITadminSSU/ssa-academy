<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\Course\SectionLesson;
use App\Services\Course\ProtectedMediaService;
use App\Services\Course\VideoPlaybackTokenService;
use App\Services\LegalAgreementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoStreamController extends Controller
{
    public function __construct(
        private ProtectedMediaService $protectedMedia,
        private VideoPlaybackTokenService $playbackTokens,
        private LegalAgreementService $legalAgreement,
    ) {}

    /**
     * Issue a short-lived signed playback URL without exposing the raw storage path.
     */
    public function streamUrl(Request $request, SectionLesson $lesson): JsonResponse
    {
        $user = Auth::user();
        $this->assertPlaybackAllowed($user, $lesson);

        if (!$this->protectedMedia->shouldSignLessonMedia($lesson)) {
            if (!$this->protectedMedia->lessonVideoIsStreamable($lesson) && in_array($lesson->lesson_type, ['video', 'video_url'], true)) {
                return response()->json([
                    'message' => 'Video file not found. Please re-upload this lesson video.',
                ], 404);
            }

            return response()->json([
                'protected' => false,
                'stream_url' => $lesson->lesson_src,
                'delivery' => 'direct',
                'expires_at' => null,
                'playback_token' => null,
            ]);
        }

        if (!$this->protectedMedia->lessonVideoIsStreamable($lesson)) {
            return response()->json([
                'message' => 'Video file not found. Please re-upload this lesson video.',
            ], 404);
        }

        $playbackToken = $this->playbackTokens->issue($user->id, $lesson->id);
        $playback = $this->protectedMedia->createVideoPlaybackPayload($lesson, $playbackToken);
        $playback['playback_token'] = $playbackToken;

        return response()->json($playback);
    }

    /**
     * Stream lesson video with HTTP Range support (signed URL entry point).
     */
    public function stream(Request $request, SectionLesson $lesson): \Symfony\Component\HttpFoundation\Response
    {
        $user = Auth::user();
        $this->assertPlaybackAllowed($user, $lesson);

        $token = $request->header('X-Playback-Token') ?? $request->query('playback_token');

        if (!$token || !$this->playbackTokens->validate((string) $token, $user->id, $lesson->id)) {
            abort(403, 'Invalid or expired video playback authorization.');
        }

        $originalSrc = $lesson->getRawOriginal('lesson_src') ?: $lesson->lesson_src;
        $mimeType = $this->protectedMedia->resolveMimeType($originalSrc, 'video/mp4');

        return $this->protectedMedia->streamMediaResponse($request, $originalSrc, $mimeType);
    }

    private function assertPlaybackAllowed($user, SectionLesson $lesson): void
    {
        if ($this->legalAgreement->requiresAcceptance($user)) {
            abort(403, 'Accept the Terms & Conditions and NDA before accessing lesson media.');
        }

        $this->protectedMedia->authorizeLessonAccess($user, $lesson);
    }
}
