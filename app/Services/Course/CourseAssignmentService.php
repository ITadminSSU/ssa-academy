<?php

namespace App\Services\Course;

use App\Models\ChunkedUpload;
use App\Models\Course\CourseAssignment;

class CourseAssignmentService extends CourseSectionService
{
   public function createAssignment(array $data): CourseAssignment
   {
      return CourseAssignment::create($data);
   }

   public function updateAssignment(array $data, string $id): bool
   {
      $assignment = CourseAssignment::findOrFail($id);

      if (
         $assignment->sample_project_type === 'file'
         && $assignment->sample_project_path
         && (
            ($data['sample_project_type'] ?? null) !== 'file'
            || ($data['sample_project_path'] ?? null) !== $assignment->sample_project_path
         )
      ) {
         $this->deleteSampleFile($assignment->sample_project_path);
      }

      return $assignment->update($data);
   }

   public function deleteAssignment(string $id): bool
   {
      $assignment = CourseAssignment::findOrFail($id);

      if ($assignment->sample_project_type === 'file' && $assignment->sample_project_path) {
         $this->deleteSampleFile($assignment->sample_project_path);
      }

      return $assignment->delete();
   }

   private function deleteSampleFile(string $fileUrl): void
   {
      $chunkedUpload = ChunkedUpload::where('file_url', $fileUrl)->first();
      $chunkedUpload && $this->uploaderService->deleteFile($chunkedUpload);
   }
}
