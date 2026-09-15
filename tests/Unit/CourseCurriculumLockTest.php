<?php

use App\Models\Course\Course;
use App\Models\Course\CourseSection;
use App\Models\Course\SectionLesson;
use App\Models\Course\SectionQuiz;
use App\Models\Course\WatchHistory;
use App\Services\Course\CourseCompletionGateService;
use App\Services\Course\LessonWatchProgressService;
use App\Services\Payment\SubscriptionAccessService;

function lockTestItem(string $class, int $id, int $sort, string $title)
{
    $model = new $class([
        'title' => $title,
        'sort' => $sort,
        'lesson_type' => $class === SectionLesson::class ? 'video' : null,
    ]);
    $model->id = $id;

    return $model;
}

function drywallLockCourse(): Course
{
    $course = new Course(['title' => 'Drywall Estimation']);
    $course->id = 30;
    $course->exists = true;

    $section = new CourseSection(['title' => 'Section 3', 'sort' => 3]);
    $section->id = 3;
    $section->setRelation('section_lessons', collect([
        lockTestItem(SectionLesson::class, 11, 1, 'Lesson 1 Video'),
        lockTestItem(SectionLesson::class, 12, 2, 'Lesson 2 Video'),
    ]));
    $section->setRelation('section_quizzes', collect([
        lockTestItem(SectionQuiz::class, 21, 3, 'Lesson 2 Quiz'),
    ]));

    $course->setRelation('sections', collect([$section]));

    return $course;
}

it('tells the student which lesson to finish before a locked quiz', function () {
    $service = new CourseCompletionGateService(
        new LessonWatchProgressService(),
        Mockery::mock(SubscriptionAccessService::class),
    );

    $course = drywallLockCourse();
    $history = new WatchHistory(['completed_watching' => []]);

    expect($service->lockMessageForItem($course, 1, 21, 'quiz', $history, []))
        ->toBe('Finish "Lesson 1 Video" before this quiz.')
        ->and($service->firstIncompletePredecessor($course, 1, 21, 'quiz', $history))
        ->toMatchArray(['id' => 11, 'type' => 'lesson']);
});

it('points at the next unfinished lesson before the quiz', function () {
    $service = new CourseCompletionGateService(
        new LessonWatchProgressService(),
        Mockery::mock(SubscriptionAccessService::class),
    );

    $course = drywallLockCourse();
    $history = new WatchHistory();
    $history->setCompletedWatchingItems([
        ['id' => 11, 'type' => 'lesson'],
    ]);
    $history->lesson_watch_progress = [
        '11' => [
            'percent' => 100,
            'max_seconds' => 120,
            'duration_seconds' => 120,
        ],
    ];

    expect($service->lockMessageForItem($course, 1, 21, 'quiz', $history, []))
        ->toBe('Finish "Lesson 2 Video" before this quiz.')
        ->and($service->firstIncompletePredecessor($course, 1, 21, 'quiz', $history))
        ->toMatchArray(['id' => 12, 'type' => 'lesson']);
});

it('does not unlock the quiz when a video is marked complete without being watched', function () {
    $service = new CourseCompletionGateService(
        new LessonWatchProgressService(),
        Mockery::mock(SubscriptionAccessService::class),
    );

    $course = drywallLockCourse();
    $history = new WatchHistory();
    $history->setCompletedWatchingItems([
        ['id' => 11, 'type' => 'lesson'],
    ]);

    expect($service->lockMessageForItem($course, 1, 21, 'quiz', $history, []))
        ->toBe('Finish "Lesson 1 Video" before this quiz.')
        ->and($service->firstIncompletePredecessor($course, 1, 21, 'quiz', $history))
        ->toMatchArray(['id' => 11, 'type' => 'lesson']);
});
