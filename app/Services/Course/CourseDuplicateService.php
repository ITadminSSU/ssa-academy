<?php

namespace App\Services\Course;

use App\Enums\CourseStatusType;
use App\Models\Course\Course;
use App\Models\Course\CourseAssignment;
use App\Models\Course\CourseLessonVideoCopy;
use App\Models\Course\CourseFaq;
use App\Models\Course\CourseLandingPage;
use App\Models\Course\CourseLiveClass;
use App\Models\Course\CourseOutcome;
use App\Models\Course\CourseRequirement;
use App\Models\Course\CourseSection;
use App\Models\Course\SectionLesson;
use App\Models\Course\SectionQuiz;
use App\Models\Course\UsExperiencePlan;
use App\Services\BunnyStreamService;
use App\Support\CourseReviewCarousel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CourseDuplicateService
{
    public function __construct(
        private BunnyStreamService $bunny,
        private OwnedMediaCopier $files,
    ) {}

    public function duplicate(Course $source, string $title): Course
    {
        $copy = $this->createDraft($source, $title);
        $this->copyPendingVideos((int) $copy->id);

        return $copy->fresh();
    }

    public function createDraft(Course $source, string $title): Course
    {
        $title = trim($title);

        if ($title === '') {
            throw new CourseDuplicateException('Enter a title for the copied course.');
        }

        $this->files->resetTracking();

        try {
            return DB::transaction(function () use ($source, $title) {
                $source->refresh();
                $copy = $this->copyCourse($source, $title);
                $sectionMap = $this->copySections($source, $copy);
                $pendingVideos = $this->copyLessons($source, $copy, $sectionMap);
                $this->copyQuizzes($source, $copy, $sectionMap);
                $this->copyAssignments($source, $copy);
                $this->copySimpleRows($source, $copy, CourseFaq::class);
                $this->copySimpleRows($source, $copy, CourseRequirement::class);
                $this->copySimpleRows($source, $copy, CourseOutcome::class);
                $this->copySimpleRows($source, $copy, CourseLiveClass::class);
                $this->copyLandingPage($source, $copy);
                $this->copyUsExperiencePlans($source, $copy);
                $this->rememberPendingVideos($copy, $pendingVideos);

                return $copy->fresh();
            });
        } catch (CourseDuplicateException $exception) {
            $this->files->rollback();
            throw $exception;
        } catch (\Throwable $exception) {
            $this->files->rollback();

            throw new CourseDuplicateException('The course could not be duplicated. Nothing on the original course was changed.', 0, $exception);
        }
    }

    public function copyPendingVideos(int $courseId): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $course = Course::query()->find($courseId);

        if (! $course) {
            return;
        }

        $pending = CourseLessonVideoCopy::query()
            ->where('course_id', $courseId)
            ->where('status', 'pending')
            ->orderBy('id')
            ->get();

        if ($pending->isEmpty()) {
            return;
        }

        $failures = [];

        if (! $this->bunny->isEnabled()) {
            foreach ($pending as $row) {
                $row->status = 'failed';
                $row->error = 'Bunny Stream is not enabled, so this lesson video was not copied.';
                $row->save();
                $failures[] = $this->pendingLessonLabel($courseId, $row).': Bunny Stream is not enabled.';
            }

            $this->storeVideoCopyResult($course, $failures);

            return;
        }

        foreach ($pending as $row) {
            $lesson = SectionLesson::query()
                ->where('course_id', $courseId)
                ->whereKey($row->section_lesson_id)
                ->first();

            if (! $lesson) {
                $row->status = 'failed';
                $row->error = 'The copied lesson no longer exists.';
                $row->save();
                $failures[] = 'A copied lesson no longer exists, so its video was not copied.';

                continue;
            }

            $sourceVideoId = trim((string) $row->source_bunny_video_id);

            try {
                $newVideoId = $this->bunny->copyVideo($sourceVideoId, $course->title.' — '.$lesson->title);
            } catch (\Throwable $exception) {
                $row->status = 'failed';
                $row->error = 'This lesson video could not be copied.';
                $row->save();
                $failures[] = $lesson->title.': this video could not be copied.';

                continue;
            }

            if ($newVideoId === '' || $newVideoId === $sourceVideoId) {
                $row->status = 'failed';
                $row->error = 'Bunny Stream did not create a separate video.';
                $row->save();
                $failures[] = $lesson->title.': Bunny Stream did not create a separate video.';

                continue;
            }

            $lesson->bunny_video_id = $newVideoId;
            $lesson->save();
            $row->status = 'copied';
            $row->error = null;
            $row->save();
        }

        $this->storeVideoCopyResult($course->fresh() ?? $course, $failures);
    }

    /**
     * @param  list<array{section_lesson_id: int, source_bunny_video_id: string}>  $pendingVideos
     */
    private function rememberPendingVideos(Course $copy, array $pendingVideos): void
    {
        if ($pendingVideos === []) {
            $copy->video_copy_status = null;
            $copy->video_copy_error = null;
            $copy->save();

            return;
        }

        foreach ($pendingVideos as $pending) {
            CourseLessonVideoCopy::query()->create([
                'course_id' => $copy->id,
                'section_lesson_id' => $pending['section_lesson_id'],
                'source_bunny_video_id' => $pending['source_bunny_video_id'],
                'status' => 'pending',
            ]);
        }

        $copy->video_copy_status = 'copying';
        $copy->video_copy_error = null;
        $copy->save();
    }

    /**
     * @param  list<string>  $failures
     */
    private function storeVideoCopyResult(Course $course, array $failures): void
    {
        if ($failures === []) {
            $course->video_copy_status = null;
            $course->video_copy_error = null;
            $course->save();

            return;
        }

        $course->video_copy_status = 'failed';
        $course->video_copy_error = "Some lesson videos could not be copied. The original course was not changed.\n".implode("\n", $failures);
        $course->save();
    }

    private function pendingLessonLabel(int $courseId, CourseLessonVideoCopy $row): string
    {
        $title = SectionLesson::query()
            ->where('course_id', $courseId)
            ->whereKey($row->section_lesson_id)
            ->value('title');

        return is_string($title) && $title !== '' ? $title : 'A lesson';
    }

    private function copyCourse(Course $source, string $title): Course
    {
        $copy = $source->replicate([
            'stripe_product_id',
            'stripe_price_id',
            'stripe_ongoing_price_id',
        ]);
        $copy->title = $title;
        $copy->slug = $this->uniqueSlug($title);
        $copy->status = CourseStatusType::DRAFT->value;
        $copy->video_copy_status = null;
        $copy->video_copy_error = null;
        $copy->stripe_product_id = null;
        $copy->stripe_price_id = null;
        $copy->stripe_ongoing_price_id = null;

        foreach (['thumbnail', 'banner', 'preview', 'hero_photo', 'hero_video', 'instructor_card_photo'] as $field) {
            $copy->{$field} = $this->files->copyValue($source->getRawOriginal($field));
        }

        $carousel = CourseReviewCarousel::storedUrls($source->getRawOriginal('review_carousel'));
        $copy->review_carousel = $carousel === [] ? null : $this->files->copyUrlList($carousel);
        $copy->save();

        return $copy;
    }

    /**
     * @return array<int, int>
     */
    private function copySections(Course $source, Course $copy): array
    {
        $map = [];

        $sections = CourseSection::query()
            ->where('course_id', $source->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        foreach ($sections as $section) {
            $next = $section->replicate();
            $next->course_id = $copy->id;
            $next->save();
            $map[(int) $section->id] = (int) $next->id;
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $sectionMap
     * @return list<array{section_lesson_id: int, source_bunny_video_id: string}>
     */
    private function copyLessons(Course $source, Course $copy, array $sectionMap): array
    {
        $pendingVideos = [];
        $lessons = SectionLesson::query()
            ->where('course_id', $source->id)
            ->with('resources')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        foreach ($lessons as $lesson) {
            $sectionId = $sectionMap[(int) $lesson->course_section_id] ?? null;

            if (! $sectionId) {
                throw new CourseDuplicateException('A lesson could not be matched to its section, so the course was not duplicated.');
            }

            $next = $lesson->replicate();
            $next->course_id = $copy->id;
            $next->course_section_id = $sectionId;

            $sourceVideoId = trim((string) $lesson->bunny_video_id);

            if ($sourceVideoId !== '') {
                $next->bunny_video_id = null;
                $next->lesson_src = null;
                $next->embed_source = null;
                $next->thumbnail = null;
                $next->save();
                $pendingVideos[] = [
                    'section_lesson_id' => (int) $next->id,
                    'source_bunny_video_id' => $sourceVideoId,
                ];
            } else {
                $next->lesson_src = $this->files->copyValue($lesson->getRawOriginal('lesson_src'));
                $next->thumbnail = $this->files->copyValue($lesson->getRawOriginal('thumbnail'));
            }

            if ($sourceVideoId === '') {
                $next->save();
            }

            foreach ($lesson->resources as $resource) {
                $copied = $resource->replicate();
                $copied->section_lesson_id = $next->id;
                $copied->resource = $this->files->copyValue($resource->getRawOriginal('resource')) ?? $resource->resource;
                $copied->save();
            }
        }

        return $pendingVideos;
    }

    /**
     * @param  array<int, int>  $sectionMap
     */
    private function copyQuizzes(Course $source, Course $copy, array $sectionMap): void
    {
        $quizzes = SectionQuiz::query()
            ->where('course_id', $source->id)
            ->with('quiz_questions')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        foreach ($quizzes as $quiz) {
            $sectionId = $sectionMap[(int) $quiz->course_section_id] ?? null;

            if (! $sectionId) {
                throw new CourseDuplicateException('A quiz could not be matched to its section, so the course was not duplicated.');
            }

            $next = $quiz->replicate();
            $next->course_id = $copy->id;
            $next->course_section_id = $sectionId;
            $next->save();

            foreach ($quiz->quiz_questions as $question) {
                $copied = $question->replicate();
                $copied->section_quiz_id = $next->id;
                $copied->options = $this->encodeJson($question->getRawOriginal('options'));
                $copied->save();
            }
        }
    }

    private function copyAssignments(Course $source, Course $copy): void
    {
        $assignments = CourseAssignment::query()
            ->where('course_id', $source->id)
            ->orderBy('id')
            ->get();

        foreach ($assignments as $assignment) {
            $next = $assignment->replicate();
            $next->course_id = $copy->id;
            $next->sample_project_path = $this->files->copyValue($assignment->getRawOriginal('sample_project_path'));
            $next->save();
        }
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function copySimpleRows(Course $source, Course $copy, string $model): void
    {
        $rows = $model::query()
            ->where('course_id', $source->id)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $next = $row->replicate();
            $next->course_id = $copy->id;
            $next->save();
        }
    }

    private function copyLandingPage(Course $source, Course $copy): void
    {
        $landing = CourseLandingPage::query()->where('course_id', $source->id)->first();

        if (! $landing) {
            return;
        }

        $next = $landing->replicate();
        $next->course_id = $copy->id;
        $next->hero_photo = $this->files->copyValue($landing->getRawOriginal('hero_photo'));
        $next->video_url = $this->files->copyValue($landing->getRawOriginal('video_url'));
        $next->reviews = $this->decodeMediaArray($landing->getRawOriginal('reviews'));
        $next->save();
    }

    private function copyUsExperiencePlans(Course $source, Course $copy): void
    {
        $plans = UsExperiencePlan::query()
            ->where('course_id', $source->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($plans as $plan) {
            $next = $plan->replicate();
            $next->course_id = $copy->id;
            $next->blank_template_file_url = $this->files->copyValue($plan->getRawOriginal('blank_template_file_url'));
            $next->answer_key_file_url = $this->files->copyValue($plan->getRawOriginal('answer_key_file_url'));
            $next->tutorial_video_url = $this->files->copyValue($plan->getRawOriginal('tutorial_video_url'));
            $next->drawings = $this->decodeMediaArray($plan->getRawOriginal('drawings'));
            $next->save();
        }
    }

    private function encodeJson(mixed $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return $raw;
        }

        $decoded = $this->decodeMediaArray($raw);

        return $decoded === null ? $raw : json_encode($decoded);
    }

    /**
     * @return array<mixed>|null
     */
    private function decodeMediaArray(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        if (! is_array($decoded)) {
            return null;
        }

        return $this->files->rewriteTree($decoded);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'course';
        }

        $slug = $base;
        $suffix = 2;

        while (Course::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
