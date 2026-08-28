<?php

use App\Enums\CourseBillingModel;
use App\Models\Course\Course;
use App\Models\Course\CourseSection;
use App\Models\Course\SectionLesson;
use App\Models\Course\SectionQuiz;
use App\Services\Course\CourseService;

function makePublicCourseWithCurriculum(): Course
{
    $course = new Course([
        'id' => 1,
        'title' => 'Test Course',
        'slug' => 'test-course',
        'instructor_id' => 10,
        'billing_model' => CourseBillingModel::SUBSCRIPTION,
        'pricing_type' => 'paid',
    ]);
    $course->exists = true;

    $section = new CourseSection([
        'id' => 5,
        'title' => 'Hidden Module',
        'sort' => 1,
    ]);
    $lesson = new SectionLesson([
        'id' => 9,
        'title' => 'Hidden Lesson',
        'duration' => '00:10:00',
    ]);
    $quiz = new SectionQuiz([
        'id' => 3,
        'title' => 'Hidden Quiz',
        'duration' => '00:05:00',
    ]);

    $section->setRelation('section_lessons', collect([$lesson]));
    $section->setRelation('section_quizzes', collect([$quiz]));
    $course->setRelation('sections', collect([$section]));

    return $course;
}

it('strips module and lesson titles but keeps duration for unauthorized public viewers', function () {
    $course = makePublicCourseWithCurriculum();
    $service = app(CourseService::class);

    $service->preparePublicCourseCurriculum($course, false);

    expect($course->sections)->toHaveCount(0);
    expect($course->duration_seconds)->toBe(900);
});

it('keeps curriculum sections when the viewer is allowed to see them', function () {
    $course = makePublicCourseWithCurriculum();
    $service = app(CourseService::class);

    $service->preparePublicCourseCurriculum($course, true);

    expect($course->sections)->toHaveCount(1);
    expect($course->sections->first()->title)->toBe('Hidden Module');
    expect($course->sections->first()->section_lessons->first()->title)->toBe('Hidden Lesson');
    expect($course->duration_seconds)->toBe(900);
});
