<?php

use App\Models\Course\WatchHistory;
use App\Services\Course\CourseCompletionGateService;
use App\Services\Course\LessonWatchProgressService;
use App\Services\Payment\SubscriptionAccessService;

function reviewUnlockService(): CourseCompletionGateService
{
    return new CourseCompletionGateService(
        new LessonWatchProgressService(),
        Mockery::mock(SubscriptionAccessService::class),
    );
}

it('keeps course reviews locked while the student is still in progress', function () {
    $history = new WatchHistory();

    expect(reviewUnlockService()->isReviewsUnlocked($history, false))->toBeFalse();
});

it('unlocks course reviews when the certificate is ready', function () {
    $history = new WatchHistory();

    expect(reviewUnlockService()->isReviewsUnlocked($history, true))->toBeTrue();
});

it('unlocks course reviews after the student finishes the course', function () {
    $history = new WatchHistory(['completion_date' => now()]);

    expect(reviewUnlockService()->isReviewsUnlocked($history, false))->toBeTrue();
});
