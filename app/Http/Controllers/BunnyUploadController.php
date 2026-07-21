<?php

namespace App\Http\Controllers;

use App\Services\BunnyStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BunnyUploadController extends Controller
{
    public function __construct(private BunnyStreamService $bunnyStream) {}

    public function initialize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filename' => 'required|string|max:255',
            'filesize' => 'nullable|numeric|min:1',
            'mimetype' => 'nullable|string|max:255',
            'course_id' => 'nullable|integer',
            'course_section_id' => 'nullable|integer',
        ]);

        if (!$this->bunnyStream->isEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Bunny Stream is not configured.',
            ], 422);
        }

        try {
            $video = $this->bunnyStream->createVideo($validated['filename']);
            $videoId = (string) ($video['guid'] ?? '');

            if ($videoId === '') {
                throw new \RuntimeException('Bunny Stream did not return a video ID.');
            }

            $credentials = $this->bunnyStream->tusUploadCredentials($videoId);

            return response()->json([
                'success' => true,
                'video_id' => $videoId,
                'upload' => $credentials,
                'message' => 'Bunny Stream upload initialized successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize Bunny Stream upload: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function complete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'video_id' => 'required|string|max:64',
        ]);

        if (!$this->bunnyStream->isEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Bunny Stream is not configured.',
            ], 422);
        }

        try {
            $result = $this->bunnyStream->completeUpload($validated['video_id']);

            return response()->json([
                'success' => true,
                'bunny_video_id' => $result['bunny_video_id'],
                'duration' => $result['duration'],
                'thumbnail' => $result['thumbnail'],
                'status' => $result['status'],
                'message' => 'Bunny Stream upload completed successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete Bunny Stream upload: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function abort(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'video_id' => 'required|string|max:64',
        ]);

        if (!$this->bunnyStream->isEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Bunny Stream is not configured.',
            ], 422);
        }

        $this->bunnyStream->deleteVideo($validated['video_id']);

        return response()->json([
            'success' => true,
            'message' => 'Bunny Stream upload aborted successfully',
        ]);
    }
}
