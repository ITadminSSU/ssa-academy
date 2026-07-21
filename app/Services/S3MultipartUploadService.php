<?php

namespace App\Services;

use App\Models\ChunkedUpload;
use App\Support\S3CompatibleStorage;
use Aws\S3\Exception\S3Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class S3MultipartUploadService
{
    protected string $bucket;

    public function __construct()
    {
        $this->bucket = (string) config('filesystems.disks.s3.bucket');
    }

    /**
     * Initialize multipart upload
     *
     * @param string $filename Original filename
     * @param string $mimeType Mime type of the file
     * @param int $fileSize Total file size
     * @param int $userId User ID
     * @param array $metadata Additional metadata
     */
    public function initiateUpload(string $filename, string $mimeType, int $fileSize, int $userId, array $metadata = []): ChunkedUpload
    {
        try {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $s3Key = 'lessons/' . Str::uuid() . ($extension !== '' ? '.' . $extension : '');

            $params = [
                'Bucket' => $this->bucket,
                'Key' => $s3Key,
                'ContentType' => $mimeType,
            ];

            if (!S3CompatibleStorage::isR2Endpoint(config('filesystems.disks.s3.endpoint'))) {
                $params['ACL'] = 'private';
            }

            $result = S3CompatibleStorage::makeClient()->createMultipartUpload($params);

            return ChunkedUpload::create([
                'user_id' => $userId,
                'filename' => $s3Key,
                'original_filename' => $filename,
                'file_path' => $s3Key,
                'disk' => 's3',
                'mime_type' => $mimeType,
                'size' => $fileSize,
                'upload_id' => $result['UploadId'],
                'key' => $s3Key,
                'status' => 'initialized',
                'chunks_completed' => 0,
                'total_chunks' => 0,
                'metadata' => $metadata,
            ]);
        } catch (S3Exception $e) {
            Log::error('S3 multipart upload initialization error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Upload a part of the file
     *
     * @return array Part information for completing the upload
     */
    public function uploadPart(ChunkedUpload $upload, int $partNumber, string $partContent): array
    {
        try {
            $result = S3CompatibleStorage::makeClient()->uploadPart([
                'Bucket' => $this->bucket,
                'Key' => $upload->key,
                'UploadId' => $upload->upload_id,
                'PartNumber' => $partNumber,
                'Body' => $partContent,
            ]);

            $upload->increment('chunks_completed');

            return [
                'PartNumber' => $partNumber,
                'ETag' => $result['ETag'],
            ];
        } catch (S3Exception $e) {
            Log::error('S3 multipart upload part error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Complete multipart upload
     *
     * @param array $parts Array of part information from uploadPart()
     */
    public function completeUpload(ChunkedUpload $upload, array $parts): bool
    {
        try {
            S3CompatibleStorage::makeClient()->completeMultipartUpload([
                'Bucket' => $this->bucket,
                'Key' => $upload->key,
                'UploadId' => $upload->upload_id,
                'MultipartUpload' => [
                    'Parts' => $parts,
                ],
            ]);

            $upload->update([
                'status' => 'completed',
                'file_url' => S3CompatibleStorage::objectFileUrl($upload->key),
            ]);

            return true;
        } catch (S3Exception $e) {
            Log::error('S3 multipart upload completion error: ' . $e->getMessage());
            $upload->update(['status' => 'failed']);
            throw $e;
        }
    }

    /**
     * Abort multipart upload
     */
    public function abortUpload(ChunkedUpload $upload): bool
    {
        try {
            if ($upload->upload_id) {
                S3CompatibleStorage::makeClient()->abortMultipartUpload([
                    'Bucket' => $this->bucket,
                    'Key' => $upload->key,
                    'UploadId' => $upload->upload_id,
                ]);
            }

            $upload->update([
                'status' => 'aborted',
            ]);

            return true;
        } catch (S3Exception $e) {
            Log::error('S3 multipart upload abort error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a file from S3
     */
    public function deleteFile(ChunkedUpload $upload): bool
    {
        $this->abortUpload($upload);

        $result = S3CompatibleStorage::makeClient()->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $upload->key,
        ]);

        if ($result) {
            $upload->delete();
            return true;
        }

        return false;
    }
}
