<?php

namespace App\Services\Course;

use App\Enums\CourseStatusType;
use App\Models\Course\Course;
use App\Models\Course\CourseAssignment;
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
        $title = trim($title);

        if ($title === '') {
            throw new CourseDuplicateException('Enter a title for the copied course.');
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $this->files->resetTracking();
        $createdVideoIds = [];

        try {
            $videoMap = $this->copyLessonVideos($source, $createdVideoIds);

            return DB::transaction(function () use ($source, $title, $videoMap) {
                $source->refresh();
                $copy = $this->copyCourse($source, $title);
                $sectionMap = $this->copySections($source, $copy);
                $this->copyLessons($source, $copy, $sectionMap, $videoMap);
                $this->copyQuizzes($source, $copy, $sectionMap);
                $this->copyAssignments($source, $copy);
                $this->copySimpleRows($source, $copy, CourseFaq::class);
                $this->copySimpleRows($source, $copy, CourseRequirement::class);
                $this->copySimpleRows($source, $copy, CourseOutcome::class);
                $this->copySimpleRows($source, $copy, CourseLiveClass::class);
                $this->copyLandingPage($source, $copy);
                $this->copyUsExperiencePlans($source, $copy);

                return $copy->fresh();
            });
        } catch (CourseDuplicateException $exception) {
            $this->cleanupFailedCopy($createdVideoIds, (int) $source->id);
            throw $exception;
        } catch (\Throwable $exception) {
            $this->cleanupFailedCopy($createdVideoIds, (int) $source->id);

            throw new CourseDuplicateException('The course could not be duplicated. Nothing on the original course was changed.', 0, $exception);
        }
    }

    /**
     * @param  list<string>  $createdVideoIds
     * @return array<int, string> source lesson id => new bunny video id
     */
    private function copyLessonVideos(Course $source, array &$createdVideoIds): array
    {
        $lessons = SectionLesson::query()
            ->where('course_id', $source->id)
            ->whereNotNull('bunny_video_id')
            ->orderBy('id')
            ->get();

        if ($lessons->isEmpty()) {
            return [];
        }

        if (! $this->bunny->isEnabled()) {
            throw new CourseDuplicateException('Bunny Stream is not enabled, so this course\'s videos cannot be copied.');
        }

        $map = [];

        foreach ($lessons as $lesson) {
            $sourceVideoId = trim((string) $lesson->bunny_video_id);

            if ($sourceVideoId === '') {
                continue;
            }

            try {
                $newVideoId = $this->bunny->copyVideo($sourceVideoId, $source->title.' — '.$lesson->title);
            } catch (CourseDuplicateException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                throw new CourseDuplicateException('A lesson video could not be copied, so the course was not duplicated.', 0, $exception);
            }

            if ($newVideoId === '' || $newVideoId === $sourceVideoId) {
                throw new CourseDuplicateException('Bunny Stream did not create a separate video, so the course was not duplicated.');
            }

            $map[(int) $lesson->id] = $newVideoId;
            $createdVideoIds[] = $newVideoId;
        }

        return $map;
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
     * @param  array<int, string>  $videoMap
     */
    private function copyLessons(Course $source, Course $copy, array $sectionMap, array $videoMap): void
    {
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
                $newVideoId = $videoMap[(int) $lesson->id] ?? '';

                if ($newVideoId === '' || $newVideoId === $sourceVideoId) {
                    throw new CourseDuplicateException('A lesson video could not be copied, so the course was not duplicated.');
                }

                $next->bunny_video_id = $newVideoId;
                $next->lesson_src = null;
                $next->embed_source = null;
                $next->thumbnail = null;
            } else {
                $next->lesson_src = $this->files->copyValue($lesson->getRawOriginal('lesson_src'));
                $next->thumbnail = $this->files->copyValue($lesson->getRawOriginal('thumbnail'));
            }

            $next->save();

            foreach ($lesson->resources as $resource) {
                $copied = $resource->replicate();
                $copied->section_lesson_id = $next->id;
                $copied->resource = $this->files->copyValue($resource->getRawOriginal('resource')) ?? $resource->resource;
                $copied->save();
            }
        }
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

    /**
     * @param  list<string>  $createdVideoIds
     */
    private function cleanupFailedCopy(array $createdVideoIds, int $sourceCourseId): void
    {
        $protected = $createdVideoIds === []
            ? []
            : SectionLesson::query()
                ->where('course_id', $sourceCourseId)
                ->whereIn('bunny_video_id', $createdVideoIds)
                ->pluck('bunny_video_id')
                ->all();

        foreach ($createdVideoIds as $videoId) {
            if (in_array($videoId, $protected, true)) {
                continue;
            }

            try {
                $this->bunny->deleteVideo($videoId);
            } catch (\Throwable) {
                // The original course is unchanged. A leftover new video can be removed in Bunny.
            }
        }

        $this->files->rollback();
    }
}
