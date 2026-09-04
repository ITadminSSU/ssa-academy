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

it('uses Build Your US Experience copy for Software welcome emails', function () {
    $paragraphs = CourseWelcomeEmailCopy::bodyParagraphs(CourseWelcomeEmailCopy::VARIANT_SOFTWARE);
    $html = CourseWelcomeEmailCopy::toHtml($paragraphs[1]);

    expect($paragraphs[0])
        ->toContain('Once you complete all the modules, you can practice your new skills using sample project plans')
        ->and($paragraphs[1])
        ->toContain('Once you complete an estimating course, you can continue honing your skills through the {em}Build Your US Experience{/em} subscription')
        ->not->toContain('Project Plans Subscription')
        ->not->toContain('continue building your skills')
        ->and($html)
        ->toContain('<em>Build Your US Experience</em>')
        ->not->toContain('{em}');
});

it('uses Build Your US Experience copy for Estimating welcome emails', function () {
    $paragraphs = CourseWelcomeEmailCopy::bodyParagraphs(CourseWelcomeEmailCopy::VARIANT_ESTIMATING);
    $html = CourseWelcomeEmailCopy::toHtml($paragraphs[0]);

    expect($paragraphs[0])
        ->toContain('Once you complete an estimating course, you can continue honing your skills through the {em}Build Your US Experience{/em} subscription')
        ->not->toContain('Project Plans Subscription')
        ->not->toContain('Once you complete all the modules')
        ->and($html)
        ->toContain('<em>Build Your US Experience</em>')
        ->not->toContain('{em}');
});

it('hides US Experience when the course has no category', function () {
    $course = new Course();
    $course->setRelation('course_category', null);

    expect(CourseWelcomeEmailCopy::showsUsExperience($course))->toBeFalse()
        ->and(CourseWelcomeEmailCopy::showsUsExperience(null))->toBeFalse();
});
