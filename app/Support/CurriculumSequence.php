<?php

namespace App\Support;

use App\Models\Course\Course;
use App\Models\Course\CourseSection;
use App\Models\Course\SectionLesson;
use App\Models\Course\SectionQuiz;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class CurriculumSequence
{
    public static function quizSortColumnExists(): bool
    {
        return Schema::hasTable('section_quizzes')
            && Schema::hasColumn('section_quizzes', 'sort');
    }

    public static function nextSort(int $sectionId): int
    {
        $lessonMax = (int) SectionLesson::query()
            ->where('course_section_id', $sectionId)
            ->max('sort');

        $quizMax = 0;

        if (self::quizSortColumnExists()) {
            $quizMax = (int) SectionQuiz::query()
                ->where('course_section_id', $sectionId)
                ->max('sort');
        }

        return max($lessonMax, $quizMax) + 1;
    }

    /**
     * @return Collection<int, array{id: int|string, type: string, sort: int}>
     */
    public static function itemsForSection(CourseSection $section): Collection
    {
        $lessons = $section->relationLoaded('section_lessons')
            ? $section->section_lessons
            : $section->section_lessons()->get();

        $quizzes = $section->relationLoaded('section_quizzes')
            ? $section->section_quizzes
            : $section->section_quizzes()->get();

        $lessonItems = $lessons->map(fn (SectionLesson $lesson) => [
            'id' => $lesson->id,
            'type' => 'lesson',
            'sort' => (int) ($lesson->sort ?? 0),
            'lesson' => $lesson,
        ]);

        $useQuizSort = $quizzes->contains(fn (SectionQuiz $quiz) => (int) ($quiz->sort ?? 0) > 0);
        $lessonMax = (int) $lessons->max('sort');

        $quizItems = $quizzes->values()->map(function (SectionQuiz $quiz, int $index) use ($useQuizSort, $lessonMax) {
            $sort = $useQuizSort
                ? (int) ($quiz->sort ?? 0)
                : $lessonMax + $index + 1;

            return [
                'id' => $quiz->id,
                'type' => 'quiz',
                'sort' => $sort,
                'quiz' => $quiz,
            ];
        });

        return $lessonItems
            ->concat($quizItems)
            ->sortBy([
                ['sort', 'asc'],
                ['type', 'asc'],
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{id: int|string, type: string, section: CourseSection, section_index: int}>
     */
    public static function flattenCourse(Course $course): Collection
    {
        $items = collect();

        foreach ($course->sections as $sectionIndex => $section) {
            foreach (self::itemsForSection($section) as $item) {
                $items->push([
                    'id' => $item['id'],
                    'type' => $item['type'],
                    'section' => $section,
                    'section_index' => $sectionIndex,
                    'sort' => $item['sort'],
                ]);
            }
        }

        return $items->values();
    }
}
