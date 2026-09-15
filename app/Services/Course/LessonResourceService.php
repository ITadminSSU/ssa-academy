<?php

namespace App\Services\Course;

use App\Models\Course\LessonResource;
use App\Models\Course\SectionLesson;
use App\Services\MediaService;
use App\Services\LocalFileUploadService;
use App\Services\S3MultipartUploadService;

class LessonResourceService extends MediaService
{
   protected LocalFileUploadService | S3MultipartUploadService $uploaderService;

   public function __construct(private ProtectedMediaService $protectedMedia)
   {
      $this->uploaderService = config('filesystems.default') === 's3' ? new S3MultipartUploadService() : new LocalFileUploadService();
   }

   function sortSectionLessons(array $sortedData): bool
   {
      foreach ($sortedData as $value) {
         SectionLesson::where('id', $value['id'])->update([
            'sort' => $value['sort']
         ]);
      }

      return true;
   }

   public function resourceStore(array $data): LessonResource
   {
      if (($data['type'] ?? '') === 'link') {
         return LessonResource::create([
            'title' => $data['title'],
            'type' => $data['type'],
            'resource' => $data['resource'] ?? '',
            'section_lesson_id' => $data['section_lesson_id'],
            'is_downloadable' => $data['is_downloadable'] ?? true,
         ]);
      }

      return LessonResource::create([
         'title' => $data['title'],
         'type' => $data['type'],
         'resource' => $data['resource_url'] ?? $data['resource'] ?? '',
         'section_lesson_id' => $data['section_lesson_id'],
         'is_downloadable' => $data['is_downloadable'] ?? true,
      ]);
   }

   public function resourceUpdate(LessonResource $resource, array $data): bool
   {
      if (($data['type'] ?? $resource->type) === 'link') {
         return $resource->update([
            'title' => $data['title'],
            'type' => $data['type'],
            'resource' => $data['resource'] ?? $resource->resource,
            'is_downloadable' => $data['is_downloadable'] ?? $resource->is_downloadable,
         ]);
      }

      $newUrl = trim((string) ($data['resource_url'] ?? ''));
      $newUrl = $newUrl !== '' ? $newUrl : null;

      if ($newUrl && $newUrl !== $resource->resource) {
         $chunkedUpload = $this->protectedMedia->findChunkedUpload($resource->resource);
         $chunkedUpload && $this->uploaderService->deleteFile($chunkedUpload);
      }

      return $resource->update([
         'title' => $data['title'],
         'type' => $data['type'],
         'resource' => $newUrl ?: $resource->resource,
         'is_downloadable' => array_key_exists('is_downloadable', $data)
            ? $data['is_downloadable']
            : $resource->is_downloadable,
      ]);
   }

   public function resourceDelete(LessonResource $resource): bool
   {
      $chunkedUpload = $this->protectedMedia->findChunkedUpload($resource->resource);
      $chunkedUpload && $this->uploaderService->deleteFile($chunkedUpload);

      $resource->delete();

      return true;
   }
}
