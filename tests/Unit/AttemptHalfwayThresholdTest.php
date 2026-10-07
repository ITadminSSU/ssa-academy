<?php

use App\Support\AttemptHalfwayThreshold;

it('uses half of ten attempts as the warning threshold', function () {
    expect(AttemptHalfwayThreshold::threshold(10))->toBe(5);
});

it('rounds odd attempt limits up so three warns on the second fail', function () {
    expect(AttemptHalfwayThreshold::threshold(3))->toBe(2);
});

it('skips the halfway warning when only one attempt is allowed', function () {
    expect(AttemptHalfwayThreshold::threshold(1))->toBeNull()
        ->and(AttemptHalfwayThreshold::shouldNotify(1, 1, false))->toBeFalse();
});

it('notifies when a failing learner first reaches the halfway attempt', function () {
    expect(AttemptHalfwayThreshold::shouldNotify(10, 5, false))->toBeTrue()
        ->and(AttemptHalfwayThreshold::shouldNotify(3, 2, false))->toBeTrue();
});

it('does not notify on a passing attempt at the halfway mark', function () {
    expect(AttemptHalfwayThreshold::shouldNotify(10, 5, true))->toBeFalse();
});

it('does not notify before the halfway attempt or after it has already been reached', function () {
    expect(AttemptHalfwayThreshold::shouldNotify(10, 4, false))->toBeFalse()
        ->and(AttemptHalfwayThreshold::shouldNotify(10, 6, false))->toBeFalse();
});
