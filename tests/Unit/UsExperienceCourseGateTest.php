<?php

use App\Models\Course\Course;
use App\Models\User;
use App\Services\Course\CourseCompletionGateService;
use App\Services\Payment\SubscriptionAccessService;
use App\Services\UsExperience\UsExperienceUnlockService;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('explains that US Experience stays locked until lessons and quizzes are done', function () {
    $service = app(CourseCompletionGateService::class);

    expect($service->usExperienceLockMessage([
        'us_experience_unlocked' => false,
        'has_quizzes' => true,
        'videos_completed' => false,
    ]))->toBe('Finish all video lessons and pass all quizzes before you can access Build Your US Experience.');

    expect($service->usExperienceLockMessage([
        'us_experience_unlocked' => false,
        'has_quizzes' => true,
        'videos_completed' => true,
    ]))->toBe('Pass all course quizzes before you can access Build Your US Experience.');

    expect($service->usExperienceLockMessage([
        'us_experience_unlocked' => false,
        'has_quizzes' => false,
        'videos_completed' => false,
    ]))->toBe('Finish all course lessons before you can access Build Your US Experience.');

    expect($service->usExperienceLockMessage([
        'us_experience_unlocked' => true,
        'has_quizzes' => true,
        'videos_completed' => true,
    ]))->toBeNull();
});

it('blocks US Experience downloads until the course curriculum is complete', function () {
    $gates = Mockery::mock(CourseCompletionGateService::class);
    $gates->shouldReceive('canAccessUsExperience')->once()->andReturn(false);

    $service = new UsExperienceUnlockService(
        Mockery::mock(SubscriptionAccessService::class),
        $gates,
    );

    $course = new Course(['title' => 'Siding Estimating']);
    $course->id = 1;
    $user = new User(['name' => 'Student']);
    $user->id = 1;

    $service->assertCourseCurriculumComplete($course, $user);
})->throws(HttpException::class, 'Finish all lessons and quizzes before accessing Build Your US Experience.');
