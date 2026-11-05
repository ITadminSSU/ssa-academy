<?php

namespace App\Jobs;

use App\Services\Course\CourseDuplicateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CopyDraftCourseVideos implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 0;

    public function __construct(public int $courseId) {}

    public function handle(CourseDuplicateService $duplicates): void
    {
        $duplicates->copyPendingVideos($this->courseId);
    }
}
