<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChunkInitiateRequest;
use App\Http\Requests\ChunkUploadRequest;
use App\Models\ChunkedUpload;
use App\Services\LocalFileUploadService;
use App\Services\S3MultipartUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChunkedUploadController extends Controller
{
    /**
     * Determine which upload service to use based on request or config
     */
    private function getUploadService(?string $disk = null): S3MultipartUploadService | LocalFileUploadService
    {
        $storageType = $disk ?? config('filesystems.default');

        return $storageType === 's3' ? new S3MultipartUploadService() : new LocalFileUploadService();
    }

    /**
     * Initialize a new chunked upload
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function initialize(ChunkInitiateRequest $request)
    {
        try {
            $storage = $request->input('storage');
            $disk = empty($storage) ? config('filesystems.default') : $storage;
            $uploaderService = $this->getUploadService($disk);

            $metadata = [
                'filetype' => $request->filetype,
                'course_id' => $request->course_id,
                'course_section_id' => $request->course_section_id,
            ];

            // Initialize the upload in the database and S3
            $upload = $uploaderService->initiateUpload(
                $request->input('filename'),
                $request->input('mimetype'),
                $request->input('filesize'),
                Auth::id(),
                $metadata
            );

            // Update total chunks
            $upload->update(['total_chunks' => $request->total_chunks]);

            $isS3 = ($upload->disk === 's3') || (($disk ?: config('filesystems.default')) === 's3');

            return response()->json([
                'success' => true,
                'key' => $upload->key,
                'upload_id' => $upload->id,
                'aws_upload_id' => $upload->upload_id,
                'disk' => $upload->disk,
                // R2/S3 require every part except the last to be >= 5MB.
                'chunk_size' => S3MultipartUploadService::MIN_PART_BYTES,
                'min_part_size' => S3MultipartUploadService::MIN_PART_BYTES,
                'direct_to_storage' => $isS3,
                'message' => 'Upload initialized successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize upload: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a presigned URL for uploading one part directly to S3/R2.
     */
    public function partUrl(Request $request, $id)
    {
        $request->validate([
            'part_number' => 'required|integer|min:1',
        ]);

        try {
            $upload = ChunkedUpload::where('id', $id)
                ->where('user_id', Auth::id())
                ->where('status', '!=', 'completed')
                ->firstOrFail();

            if ($upload->disk !== 's3') {
                return response()->json([
                    'success' => false,
                    'message' => 'Direct part uploads are only available for S3/R2 storage.',
                ], 422);
            }

            $uploaderService = $this->getUploadService('s3');
            $url = $uploaderService->createPartUploadUrl($upload, (int) $request->input('part_number'));

            return response()->json([
                'success' => true,
                'url' => $url,
                'part_number' => (int) $request->input('part_number'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create part upload URL: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Record a part that was uploaded directly to S3/R2.
     */
    public function partAck(Request $request, $id)
    {
        $request->validate([
            'part_number' => 'required|integer|min:1',
            'etag' => 'required|string',
            'size' => 'required|integer|min:1',
        ]);

        try {
            $upload = ChunkedUpload::where('id', $id)
                ->where('user_id', Auth::id())
                ->where('status', '!=', 'completed')
                ->firstOrFail();

            $partNumber = (int) $request->input('part_number');
            $size = (int) $request->input('size');
            $isLastPart = $partNumber >= (int) $upload->total_chunks;

            if (! $isLastPart && $size < S3MultipartUploadService::MIN_PART_BYTES) {
                return response()->json([
                    'success' => false,
                    'message' => 'Each upload part except the last must be at least 5MB for Cloudflare R2.',
                ], 422);
            }

            $etag = trim((string) $request->input('etag'));

            DB::table('chunked_upload_parts')->updateOrInsert(
                [
                    'upload_id' => $upload->id,
                    'part_number' => $partNumber,
                ],
                [
                    'etag' => $etag,
                    'size' => $size,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $completed = DB::table('chunked_upload_parts')
                ->where('upload_id', $upload->id)
                ->count();

            $upload->update([
                'chunks_completed' => $completed,
                'status' => 'uploading',
            ]);

            return response()->json([
                'success' => true,
                'part_number' => $partNumber,
                'percentage' => $upload->fresh()->percentCompleted(),
                'chunks_completed' => $completed,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to acknowledge part: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload a chunk of the file
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadChunk(ChunkUploadRequest $request, $id)
    {
        try {
            // Find the upload record
            $upload = ChunkedUpload::where('id', $id)
                ->where('user_id', Auth::id())
                ->where('status', '!=', 'completed')
                ->firstOrFail();

            // Determine upload service based on the stored disk type
            $uploaderService = $this->getUploadService($upload->disk);

            $chunk = null;

            if ($request->hasFile('chunk')) {
                $uploadedChunk = $request->file('chunk');

                if (! $uploadedChunk || ! $uploadedChunk->isValid()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid chunk upload.',
                    ], 400);
                }

                $chunk = file_get_contents($uploadedChunk->getRealPath());
            } else {
                // Legacy base64 JSON payload
                $encodedData = (string) $request->input('chunk_data');
                $commaPos = strpos($encodedData, ',');
                $base64Content = $commaPos === false ? $encodedData : substr($encodedData, $commaPos + 1);
                $chunk = base64_decode($base64Content, true);
            }

            if ($chunk === false || $chunk === null || $chunk === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to read chunk data',
                ], 400);
            }

            $partNumber = (int) $request->input('part_number');
            $chunkSize = strlen($chunk);
            $isLastPart = $partNumber >= (int) $upload->total_chunks;

            if ($upload->disk === 's3' && ! $isLastPart && $chunkSize < S3MultipartUploadService::MIN_PART_BYTES) {
                return response()->json([
                    'success' => false,
                    'message' => 'Each upload part except the last must be at least 5MB for Cloudflare R2. Hard-refresh the page after deploy, or raise Nginx client_max_body_size to 20M.',
                ], 422);
            }

            // Upload the part to S3
            $part = $uploaderService->uploadPart($upload, $partNumber, $chunk);

            // Store part information in the database (for completing the upload later)
            DB::table('chunked_upload_parts')->updateOrInsert(
                [
                    'upload_id' => $upload->id,
                    'part_number' => $partNumber,
                ],
                [
                    'etag' => $part['ETag'],
                    'size' => $chunkSize,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            return response()->json([
                'success' => true,
                'part_number' => $partNumber,
                'etag' => $part['ETag'],
                'percentage' => $upload->percentCompleted(),
                'chunks_completed' => $upload->chunks_completed,
                'message' => 'Chunk uploaded successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload chunk: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete the chunked upload
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function complete(Request $request, $id)
    {
        try {
            $upload = ChunkedUpload::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $uploaderService = $this->getUploadService($upload->disk);

            if ($upload->status === 'completed') {
                return response()->json([
                    'success' => true,
                    'message' => 'Upload already completed',
                    'file_path' => $upload->file_path,
                ]);
            }

            if ($upload->chunks_completed < $upload->total_chunks) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot complete upload, not all chunks have been uploaded'
                ], 422);
            }

            // Get all parts from database (dedupe by part number)
            $parts = DB::table('chunked_upload_parts')
                ->where('upload_id', $upload->id)
                ->orderBy('part_number')
                ->get()
                ->unique('part_number')
                ->values();

            if ($upload->disk === 's3') {
                $undersized = $parts->filter(function ($part, $index) use ($parts) {
                    $isLast = $index === $parts->count() - 1;

                    return ! $isLast && (int) $part->size < S3MultipartUploadService::MIN_PART_BYTES;
                });

                if ($undersized->isNotEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Upload parts are smaller than Cloudflare R2 allows (5MB minimum per part except the last). Hard-refresh after deploy and ensure Forge Nginx client_max_body_size is at least 20M, or paste a YouTube/Vimeo URL.',
                    ], 422);
                }
            }

            $partsPayload = $parts->map(function ($part) {
                return [
                    'PartNumber' => (int) $part->part_number,
                    'ETag' => $part->etag,
                ];
            })->toArray();

            // Complete the upload
            $uploaderService->completeUpload($upload, $partsPayload);
            $upload->refresh();

            // After successful completion
            DB::table('chunked_upload_parts')
                ->where('upload_id', $upload->id)
                ->delete();

            // Return file information
            return response()->json([
                'success' => true,
                'message' => 'Upload completed successfully',
                'file_path' => $upload->file_path,
                'file_url' => $upload->file_url,
                'mime_type' => $upload->mime_type,
                'file_name' => $upload->original_filename,
                'file_size' => $upload->size,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete upload: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check upload status
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function status($id)
    {
        try {
            // Find the upload record
            $upload = ChunkedUpload::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'status' => $upload->status,
                'chunks_completed' => $upload->chunks_completed,
                'total_chunks' => $upload->total_chunks,
                'percentage' => $upload->percentCompleted(),
                'file_path' => $upload->status === 'completed' ? $upload->file_path : null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check upload status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Abort upload
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function abort(Request $request, $id)
    {
        try {
            // Find the upload record
            $upload = ChunkedUpload::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            // Determine upload service based on the stored disk type
            $uploaderService = $this->getUploadService($upload->disk);

            // Abort the upload
            $uploaderService->abortUpload($upload);

            return response()->json([
                'success' => true,
                'message' => 'Upload aborted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to abort upload: ' . $e->getMessage()
            ], 500);
        }
    }
}
