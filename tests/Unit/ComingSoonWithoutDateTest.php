<?php

use App\Enums\CourseStatusType;
use App\Models\Course\Course;

it('treats a dateless coming soon course as listed but not open', function () {
    $course = new Course([
        'status' => CourseStatusType::COMING_SOON->value,
        'launch_at' => null,
    ]);

    expect($course->isComingSoon())->toBeTrue()
        ->and($course->isCatalogListed())->toBeTrue()
        ->and($course->isEnrollmentOpen())->toBeFalse();
});

it('keeps a dated upcoming course coming soon until the launch date', function () {
    $course = new Course([
        'status' => CourseStatusType::UPCOMING->value,
        'launch_at' => now()->addWeek(),
    ]);

    expect($course->isComingSoon())->toBeTrue()
        ->and($course->isEnrollmentOpen())->toBeFalse()
        ->and($course->launch_at)->not->toBeNull();
});

it('does not treat an approved course without a launch date as coming soon', function () {
    $course = new Course([
        'status' => CourseStatusType::APPROVED->value,
        'launch_at' => null,
    ]);

    expect($course->isComingSoon())->toBeFalse()
        ->and($course->isEnrollmentOpen())->toBeTrue();
});
