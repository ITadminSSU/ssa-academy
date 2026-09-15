<?php

use App\Models\Course\Course;
use App\Models\Course\CourseSection;
use App\Models\Course\SectionLesson;
use App\Models\Course\SectionQuiz;
use App\Support\CurriculumSequence;

function makeCurriculumItem(string $class, int $id, int $sort, string $title)
{
    $model = new $class([
        'title' => $title,
        'sort' => $sort,
    ]);
    $model->id = $id;

    return $model;
}

it('interleaves lessons and quizzes by shared sort', function () {
    $section = new CourseSection(['id' => 1, 'title' => 'Module 1']);
    $section->id = 1;
    $section->setRelation('section_lessons', collect([
        makeCurriculumItem(SectionLesson::class, 11, 1, 'Video 1'),
        makeCurriculumItem(SectionLesson::class, 12, 3, 'Video 2'),
    ]));
    $section->setRelation('section_quizzes', collect([
        makeCurriculumItem(SectionQuiz::class, 21, 2, 'Quiz 1'),
    ]));

    $items = CurriculumSequence::itemsForSection($section);

    expect($items->pluck('type')->all())->toBe(['lesson', 'quiz', 'lesson']);
    expect($items->pluck('id')->all())->toBe([11, 21, 12]);
});

it('keeps quizzes after lessons when quiz sort is unset', function () {
    $section = new CourseSection(['id' => 2, 'title' => 'Module 2']);
    $section->id = 2;
    $section->setRelation('section_lessons', collect([
        makeCurriculumItem(SectionLesson::class, 11, 1, 'Video 1'),
        makeCurriculumItem(SectionLesson::class, 12, 2, 'Video 2'),
    ]));
    $section->setRelation('section_quizzes', collect([
        makeCurriculumItem(SectionQuiz::class, 21, 0, 'Quiz 1'),
    ]));

    $items = CurriculumSequence::itemsForSection($section);

    expect($items->pluck('type')->all())->toBe(['lesson', 'lesson', 'quiz']);
    expect($items->pluck('id')->all())->toBe([11, 12, 21]);
});

it('flattens sections by sort even when they were loaded in id order', function () {
    $course = new Course(['title' => 'Drywall']);
    $course->id = 13;
    $course->exists = true;

    $module = new CourseSection(['title' => 'Module 1', 'sort' => 2]);
    $module->id = 10;
    $module->setRelation('section_lessons', collect([
        makeCurriculumItem(SectionLesson::class, 12, 1, 'What is Drywall'),
    ]));
    $module->setRelation('section_quizzes', collect());

    $intro = new CourseSection(['title' => 'Introduction', 'sort' => 1]);
    $intro->id = 20;
    $intro->setRelation('section_lessons', collect([
        makeCurriculumItem(SectionLesson::class, 11, 1, 'Welcome'),
    ]));
    $intro->setRelation('section_quizzes', collect());

    $course->setRelation('sections', collect([$module, $intro]));

    $items = CurriculumSequence::flattenCourse($course);

    expect($items->pluck('id')->all())->toBe([11, 12]);
});

it('flattens a course in mixed section order', function () {
    $course = new Course(['title' => 'Course']);
    $course->id = 9;
    $course->exists = true;

    $section = new CourseSection(['title' => 'Module 1']);
    $section->id = 1;
    $section->setRelation('section_lessons', collect([
        makeCurriculumItem(SectionLesson::class, 11, 1, 'Video 1'),
        makeCurriculumItem(SectionLesson::class, 12, 3, 'Video 2'),
    ]));
    $section->setRelation('section_quizzes', collect([
        makeCurriculumItem(SectionQuiz::class, 21, 2, 'Quiz 1'),
    ]));
    $course->setRelation('sections', collect([$section]));

    $items = CurriculumSequence::flattenCourse($course);

    expect($items->pluck('type')->all())->toBe(['lesson', 'quiz', 'lesson']);
    expect($items->pluck('id')->all())->toBe([11, 21, 12]);
});
