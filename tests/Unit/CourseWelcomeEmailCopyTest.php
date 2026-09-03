<?php

use App\Models\Course\Course;
use App\Models\Course\CourseCategory;
use App\Support\CourseWelcomeEmailCopy;

function courseWithCategory(?string $slug, ?string $title): Course
{
    $course = new Course();
    $course->setRelation('course_category', new CourseCategory([
        'slug' => $slug ?? '',
        'title' => $title ?? '',
    ]));

    return $course;
}

it('shows Build Your US Experience only for Estimating courses', function () {
    expect(CourseWelcomeEmailCopy::showsUsExperience(courseWithCategory('estimating-course', 'Estimating Course')))->toBeTrue()
        ->and(CourseWelcomeEmailCopy::showsUsExperience(courseWithCategory('software-training', 'Software Training')))->toBeFalse()
        ->and(CourseWelcomeEmailCopy::showsUsExperience(courseWithCategory('career-readiness', 'Career Readiness')))->toBeFalse()
        ->and(CourseWelcomeEmailCopy::showsUsExperience(courseWithCategory('professional-development', 'Professional Development')))->toBeFalse();
});

it('hides US Experience when software is in the category name even with estimating', function () {
    expect(CourseWelcomeEmailCopy::showsUsExperience(courseWithCategory('estimating-software', 'Estimating Software')))->toBeFalse();
});

it('hides US Experience when the course has no category', function () {
    $course = new Course();
    $course->setRelation('course_category', null);

    expect(CourseWelcomeEmailCopy::showsUsExperience($course))->toBeFalse()
        ->and(CourseWelcomeEmailCopy::showsUsExperience(null))->toBeFalse();
});
