<?php

namespace App\Services;

use App\Models\ChunkedUpload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class LocalFileUploadService
{
   protected string $disk;

   public function __construct()
   {
      $this->disk = 'public';
   }

   public function initiateUpload(string $filename, string $mimeType, int $fileSize, int $userId, array $metadata = [])
   {
      $extension = pathinfo($filename, PATHINFO_EXTENSION);
      $filePath = 'lessons/' . Str::uuid() . '.' . $extension;

      // Create directory if it doesn't exist
      Storage::disk($this->disk)->makeDirectory(dirname($filePath));

      $uploadRecord = ChunkedUpload::create([
         'user_id' => $userId,
         'filename' => $filePath,
         'original_filename' => $filename,
         'file_path' => $filePath,
         'disk' => $this->disk,
         'mime_type' => $mimeType,
         'size' => $fileSize,
         'upload_id' => (string) Str::uuid(),
         'key' => $filePath,
         'status' => 'initialized',
         'chunks_completed' => 0,
         'total_chunks' => 0,
         'metadata' => $metadata,
      ]);

      return $uploadRecord;
   }

   public function uploadPart($upload, int $partNumber, string $partContent): array
   {
      $chunkPath = $upload->key . '.part' . $partNumber;
      Storage::disk($this->disk)->put($chunkPath, $partContent);

      $upload->increment('chunks_completed');

      return [
         'PartNumber' => $partNumber,
         'ETag' => md5($partContent),
         // Don't include chunk_path in the response as it can be reconstructed
      ];
   }

   public function completeUpload($upload, array $parts): bool
   {
      try {
         if (empty($parts)) {
            throw new \RuntimeException('Upload failed: no video chunks were received.');
         }

         // Sort parts by part number
         usort($parts, fn($a, $b) => $a['PartNumber'] <=> $b['PartNumber']);

         // Combine all chunks
         $finalPath = $upload->key;
         $finalDiskPath = Storage::disk($this->disk)->path($finalPath);
         $finalHandle = fopen($finalDiskPath, 'wb');

         if ($finalHandle === false) {
            throw new \RuntimeException('Upload failed: could not create the video file.');
         }

         $mergedBytes = 0;
         $missingChunks = 0;

         foreach ($parts as $part) {
            // Handle both cases: when chunk_path is provided or needs to be constructed
            $chunkPath = $part['chunk_path'] ?? ($upload->key . '.part' . $part['PartNumber']);

            if (!Storage::disk($this->disk)->exists($chunkPath)) {
               Log::error("Chunk file not found: " . $chunkPath);
               $missingChunks++;
               continue;
            }

            $chunkContent = Storage::disk($this->disk)->get($chunkPath);
            fwrite($finalHandle, $chunkContent);
            $mergedBytes += strlen($chunkContent);
            Storage::disk($this->disk)->delete($chunkPath);
         }

         fclose($finalHandle);

         if ($missingChunks > 0 || $mergedBytes === 0 || !Storage::disk($this->disk)->exists($finalPath)) {
            Storage::disk($this->disk)->delete($finalPath);
            throw new \RuntimeException('Upload failed: the video file could not be assembled. Please try uploading again.');
         }

         $actualSize = Storage::disk($this->disk)->size($finalPath);

         if ($upload->size > 0 && $actualSize < (int) floor($upload->size * 0.9)) {
            Storage::disk($this->disk)->delete($finalPath);
            throw new \RuntimeException('Upload failed: the saved video file is incomplete. Please try uploading again.');
         }

         // Generate the local file URL
         $fileUrl = public_asset_url(asset('storage/' . $upload->key));

         $upload->update([
            'status' => 'completed',
            'file_url' => $fileUrl,
         ]);

         return true;
      } catch (\Exception $e) {
         Log::error('Local file upload completion error: ' . $e->getMessage());
         $upload->update(['status' => 'failed']);
         throw $e;
      }
   }

   public function abortUpload($upload): bool
   {
      try {
         // Delete any partial chunks
         $chunks = Storage::disk($this->disk)->files(dirname($upload->key));
         foreach ($chunks as $chunk) {
            if (str_starts_with($chunk, $upload->key . '.part')) {
               Storage::disk($this->disk)->delete($chunk);
            }
         }

         // Delete the final file if it exists
         if (Storage::disk($this->disk)->exists($upload->key)) {
            Storage::disk($this->disk)->delete($upload->key);
         }

         $upload->update(['status' => 'aborted']);
         return true;
      } catch (\Exception $e) {
         Log::error('Local upload abort error: ' . $e->getMessage());
         return false;
      }
   }

   public function deleteFile(ChunkedUpload $upload): bool
   {
      // Clean up any remaining chunks
      $this->abortUpload($upload);

      if (Storage::disk($this->disk)->exists($upload->key)) {
         Storage::disk($this->disk)->delete($upload->key);
         Storage::disk($this->disk)->deleteDirectory(dirname($upload->key));

         $upload->delete();

         return true;
      }

      return false;
   }
}
