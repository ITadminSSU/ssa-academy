<?php

use App\Models\Course\Course;
use App\Models\Course\CourseSection;
use App\Models\Course\SectionLesson;
use App\Models\Course\SectionQuiz;
use App\Models\Course\WatchHistory;
use App\Services\Course\CourseStudentProgressService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

function positionCourse(): Course
{
    $estimating = new CourseSection(['title' => 'Estimating', 'sort' => 1]);
    $estimating->id = 1;
    $intro = new SectionLesson(['title' => 'Intro', 'sort' => 1, 'lesson_type' => 'document']);
    $intro->id = 11;
    $reading = new SectionLesson(['title' => 'Reading plans', 'sort' => 2, 'lesson_type' => 'video']);
    $reading->id = 12;
    $check = new SectionQuiz(['title' => 'Module check', 'sort' => 3]);
    $check->id = 21;
    $estimating->setRelation('section_lessons', collect([$intro, $reading]));
    $estimating->setRelation('section_quizzes', collect([$check]));

    $takeoff = new CourseSection(['title' => 'Takeoff', 'sort' => 2]);
    $takeoff->id = 2;
    $plans = new SectionLesson(['title' => 'Plans', 'sort' => 1, 'lesson_type' => 'video']);
    $plans->id = 13;
    $takeoff->setRelation('section_lessons', collect([$plans]));
    $takeoff->setRelation('section_quizzes', new Collection);

    $course = new Course(['title' => 'EVT']);
    $course->id = 5;
    $course->setRelation('sections', collect([$takeoff, $estimating]));

    return $course;
}

function positionHistory(array $attributes = []): WatchHistory
{
    $history = new WatchHistory(array_merge([
        'current_watching_id' => '12',
        'current_watching_type' => 'lesson',
        'completed_watching' => [],
        'lesson_watch_progress' => [],
    ], $attributes));
    $history->updated_at = Carbon::parse('2026-03-01 15:04:05', 'UTC');

    return $history;
}

function outlineItem(array $position, string $id): ?array
{
    foreach ($position['outline'] as $section) {
        foreach ($section['items'] as $item) {
            if ($item['id'] === $id) {
                return $item;
            }
        }
    }

    return null;
}

it('describes where a student is without changing the roster scores', function () {
    $service = app(CourseStudentProgressService::class);
    $course = positionCourse();
    $emptyCompletion = ['total_items' => 4, 'completed_items' => 0, 'completion' => 0];

    $notStarted = $service->buildCoursePosition($course, null, $emptyCompletion);

    expect($notStarted['status'])->toBe('not_started')
        ->and($notStarted['now_on'])->toBeNull()
        ->and($notStarted['last_finished'])->toBeNull()
        ->and($notStarted['last_active_at'])->toBeNull()
        ->and(outlineItem($notStarted, '12')['status'])->toBe('not_started');

    $onLesson = $service->buildCoursePosition($course, positionHistory(), $emptyCompletion);

    expect($onLesson['status'])->toBe('in_progress')
        ->and($onLesson['now_on']['section_title'])->toBe('Estimating')
        ->and($onLesson['now_on']['item_title'])->toBe('Reading plans')
        ->and($onLesson['now_on']['item_type'])->toBe('lesson')
        ->and($onLesson['now_on']['video_percent'])->toBeNull()
        ->and($onLesson['last_finished'])->toBeNull()
        ->and($onLesson['last_active_at'])->toBe('2026-03-01T15:04:05+00:00');

    $onQuiz = $service->buildCoursePosition($course, positionHistory([
        'current_watching_id' => '21',
        'current_watching_type' => 'quiz',
    ]), $emptyCompletion);

    expect($onQuiz['now_on']['item_title'])->toBe('Module check')
        ->and($onQuiz['now_on']['item_type'])->toBe('quiz')
        ->and($onQuiz['now_on']['video_percent'])->toBeNull();

    $missing = $service->buildCoursePosition($course, positionHistory([
        'current_watching_id' => '999',
        'current_watching_type' => 'lesson',
        'completed_watching' => [
            ['id' => 11, 'type' => 'lesson'],
        ],
    ]), ['total_items' => 4, 'completed_items' => 1, 'completion' => 25]);

    expect($missing['now_on']['unavailable'])->toBeTrue()
        ->and($missing['now_on']['item_title'])->toBe('Current item unavailable')
        ->and($missing['last_finished']['item_title'])->toBe('Intro');

    $furthest = $service->buildCoursePosition($course, positionHistory([
        'current_watching_id' => '11',
        'completed_watching' => [
            ['id' => '11', 'type' => 'lesson'],
            ['id' => 13, 'type' => 'lesson'],
        ],
    ]), ['total_items' => 4, 'completed_items' => 2, 'completion' => 50]);

    expect($furthest['now_on']['item_title'])->toBe('Intro')
        ->and($furthest['last_finished']['section_title'])->toBe('Takeoff')
        ->and($furthest['last_finished']['item_title'])->toBe('Plans');

    $watching = $service->buildCoursePosition($course, positionHistory([
        'current_watching_id' => 12,
        'completed_watching' => [
            ['id' => '11', 'type' => 'lesson'],
        ],
        'lesson_watch_progress' => [
            11 => ['percent' => 100],
            '12' => ['percent' => 40],
        ],
    ]), ['total_items' => 4, 'completed_items' => 1, 'completion' => 25]);

    expect($watching['now_on']['video_percent'])->toBe(40.0)
        ->and(outlineItem($watching, '12')['status'])->toBe('watching')
        ->and(outlineItem($watching, '12')['video_percent'])->toBe(40.0)
        ->and(outlineItem($watching, '11')['status'])->toBe('finished')
        ->and(outlineItem($watching, '11')['video_percent'])->toBeNull()
        ->and(outlineItem($watching, '13')['video_percent'])->toBeNull();

    $rewatch = $service->buildCoursePosition($course, positionHistory([
        'current_watching_id' => '12',
        'completed_watching' => [
            ['id' => 12, 'type' => 'lesson'],
        ],
        'lesson_watch_progress' => [
            '12' => ['percent' => 100],
        ],
    ]), ['total_items' => 4, 'completed_items' => 1, 'completion' => 25]);

    expect($rewatch['now_on']['video_percent'])->toBeNull()
        ->and(outlineItem($rewatch, '12')['status'])->toBe('finished')
        ->and(outlineItem($rewatch, '12')['current'])->toBeTrue()
        ->and(outlineItem($rewatch, '12')['video_percent'])->toBeNull();

    $completion = ['total_items' => 4, 'completed_items' => 4, 'completion' => 100];
    $before = $completion;
    $finished = $service->buildCoursePosition($course, positionHistory([
        'current_watching_id' => '13',
        'completed_watching' => [
            ['id' => '11', 'type' => 'lesson'],
            ['id' => '12', 'type' => 'lesson'],
            ['id' => '21', 'type' => 'quiz'],
            ['id' => '13', 'type' => 'lesson'],
        ],
    ]), $completion);

    expect($completion)->toBe($before)
        ->and($finished['status'])->toBe('finished')
        ->and($finished['last_finished']['item_title'])->toBe('Plans');

    $markedFinished = positionHistory([
        'completion_date' => '2026-03-02 00:00:00',
        'completed_watching' => [
            ['id' => '11', 'type' => 'lesson'],
        ],
    ]);
    $closed = $service->buildCoursePosition($course, $markedFinished, ['total_items' => 4, 'completed_items' => 1, 'completion' => 25]);

    expect($closed['status'])->toBe('finished')
        ->and($closed['last_finished']['item_title'])->toBe('Intro');

    $scores = (new ReflectionMethod(CourseStudentProgressService::class, 'calculateStudentScores'))
        ->invoke($service, [
            ['marks_obtained' => 60, 'total_mark' => 100],
        ], [
            ['attempted' => true, 'total_mark' => 100, 'score' => 80],
        ]);

    expect($scores['overall_score_percent'])->toBe(70.0)
        ->and($scores['best_quiz_percent'])->toBe(80.0);

    $amy = new stdClass;
    $amy->user = new stdClass;
    $amy->user->name = 'Amy';
    $zoe = new stdClass;
    $zoe->user = new stdClass;
    $zoe->user->name = 'Zoe';

    $rows = collect([
        [
            'enrollment' => $zoe,
            'completion' => ['completion' => 10],
            'overall_score_percent' => 10,
            'best_quiz_percent' => 20,
            'position' => $finished,
        ],
        [
            'enrollment' => $amy,
            'completion' => ['completion' => 90],
            'overall_score_percent' => $scores['overall_score_percent'],
            'best_quiz_percent' => $scores['best_quiz_percent'],
            'position' => $finished,
        ],
    ]);

    $byScore = (new ReflectionMethod(CourseStudentProgressService::class, 'sortStudents'))
        ->invoke($service, $rows, 'overall_score')
        ->values();

    expect($byScore[0]['enrollment']->user->name)->toBe('Amy')
        ->and($byScore[0]['overall_score_percent'])->toBe(70.0)
        ->and($byScore[0]['best_quiz_percent'])->toBe(80.0)
        ->and($byScore[0]['completion']['completion'])->toBe(90)
        ->and($byScore[1]['completion']['completion'])->toBe(10);
});
