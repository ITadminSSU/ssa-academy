<?php

use App\Enums\EnrollmentAccessStatus;
use App\Models\Course\CourseEnrollment;
use App\Models\Setting;
use App\Services\Course\CompanionCourseEnrollmentService;
use App\Services\Course\CourseEnrollmentService;
use App\Services\SettingsService;

function companionSettings(?int $courseId, bool $enabled = true): SettingsService
{
    $setting = new Setting([
        'fields' => [
            'companion_auto_enroll_enabled' => $enabled,
            'companion_course_id' => $courseId,
        ],
    ]);

    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('getSetting')->andReturn($setting);

    return $settings;
}

function companionService(SettingsService $settings, ?CourseEnrollmentService $enrollments = null): CompanionCourseEnrollmentService
{
    return Mockery::mock(
        CompanionCourseEnrollmentService::class,
        [$enrollments ?? Mockery::mock(CourseEnrollmentService::class), $settings]
    )->makePartial();
}

it('skips grant when auto-enroll is off', function () {
    $service = companionService(companionSettings(5, enabled: false));
    $service->shouldNotReceive('grantToUser');

    $enrollment = new CourseEnrollment([
        'user_id' => 1,
        'course_id' => 9,
        'access_status' => EnrollmentAccessStatus::ACTIVE,
    ]);

    expect($service->isEnabled())->toBeFalse()
        ->and($service->grantForEnrollment($enrollment))->toBeNull();
});

it('skips grant for deposit-only reserved seats', function () {
    $service = companionService(companionSettings(5));
    $service->shouldNotReceive('grantToUser');

    $enrollment = new CourseEnrollment([
        'user_id' => 1,
        'course_id' => 9,
        'access_status' => EnrollmentAccessStatus::RESERVED,
    ]);

    expect($service->grantForEnrollment($enrollment))->toBeNull();
});

it('grants the companion course when the source enrollment is active', function () {
    $granted = new CourseEnrollment([
        'user_id' => 1,
        'course_id' => 5,
        'access_status' => EnrollmentAccessStatus::ACTIVE,
    ]);

    $service = companionService(companionSettings(5));
    $service->shouldReceive('grantToUser')->once()->with(1, 5)->andReturn($granted);

    $enrollment = new CourseEnrollment([
        'user_id' => 1,
        'course_id' => 9,
        'access_status' => EnrollmentAccessStatus::ACTIVE,
    ]);

    expect($service->isEnabled())->toBeTrue()
        ->and($service->grantForEnrollment($enrollment))->toBe($granted);
});

it('does not enroll again when the learner joined the companion course itself', function () {
    $service = companionService(companionSettings(5));
    $service->shouldNotReceive('grantToUser');

    $enrollment = new CourseEnrollment([
        'user_id' => 1,
        'course_id' => 5,
        'access_status' => EnrollmentAccessStatus::ACTIVE,
    ]);

    expect($service->grantForEnrollment($enrollment))->toBeNull();
});

it('treats an empty companion course id as disabled', function () {
    $service = companionService(companionSettings(null, enabled: true));

    expect($service->isEnabled())->toBeFalse()
        ->and($service->grantForExistingLearners()['granted'])->toBe(0);
});

it('does not add companion welcome copy when auto-enroll is off', function () {
    $source = new \App\Models\Course\Course(['title' => 'Building a Winning Resume']);
    $source->id = 9;

    expect(companionService(companionSettings(5, enabled: false))->welcomeCompanionCourse($source))->toBeNull();
});

it('does not add companion welcome copy when the source course is the gift course', function () {
    $source = new \App\Models\Course\Course(['title' => 'Academy Orientation']);
    $source->id = 5;

    expect(companionService(companionSettings(5))->welcomeCompanionCourse($source))->toBeNull();
});
