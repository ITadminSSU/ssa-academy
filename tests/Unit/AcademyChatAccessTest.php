<?php

use App\Enums\ChatConversationType;
use App\Models\ChatConversation;
use App\Models\User;
use App\Services\Chat\ChatAccessService;
use App\Services\Payment\SubscriptionAccessService;

it('lets admin and operations admin message a student who is not enrolled', function () {
    $access = new ChatAccessService(Mockery::mock(SubscriptionAccessService::class));

    $admin = new User(['role' => 'admin', 'name' => 'Ada']);
    $admin->id = 1;
    $ops = new User(['role' => 'admin', 'name' => 'Omar', 'can_manage_platform_settings' => false]);
    $ops->id = 2;
    $student = new User(['role' => 'student', 'name' => 'Sam']);
    $student->id = 9;
    $trainer = new User(['role' => 'instructor', 'name' => 'Tess']);
    $trainer->id = 4;

    expect($access->canMessageStudentAsAcademy($admin, $student))->toBeTrue()
        ->and($access->canMessageStudentAsAcademy($ops, $student))->toBeTrue()
        ->and($access->canMessageStudentAsAcademy($trainer, $student))->toBeFalse()
        ->and($access->canMessageStudentAsAcademy($student, $student))->toBeFalse()
        ->and($access->canMessageStudentAsAcademy($admin, $trainer))->toBeFalse();
});

it('keeps academy threads off the course instructor path', function () {
    $conversation = new ChatConversation([
        'type' => ChatConversationType::Academy,
        'title' => 'Academy',
        'course_id' => null,
        'student_user_id' => 9,
    ]);

    expect($conversation->isAcademy())->toBeTrue()
        ->and($conversation->course_id)->toBeNull();
});
