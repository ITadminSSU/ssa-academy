<?php

use App\Services\Course\LessonWatchProgressService;

it('keeps sequential watch time and ignores a skip-ahead jump', function () {
    $service = new LessonWatchProgressService();

    $started = $service->applyProgressUpdate([
        'percent' => 0,
        'max_seconds' => 12,
        'duration_seconds' => 120,
    ], 13, 120);

    expect($started['max_seconds'])->toBe(13.0)
        ->and($started['percent'])->toBe(10.83);

    $skipped = $service->applyProgressUpdate($started, 118, 120);

    expect($skipped['max_seconds'])->toBe(13.0)
        ->and($skipped['percent'])->toBe(10.83);
});

it('rejects sentinel full-progress values unless seek jumps are allowed', function () {
    $service = new LessonWatchProgressService();
    $existing = [
        'percent' => 10,
        'max_seconds' => 12,
        'duration_seconds' => 120,
    ];

    $blocked = $service->applyProgressUpdate($existing, 999999, 999999);

    expect($blocked['max_seconds'])->toBe(12.0)
        ->and($blocked['duration_seconds'])->toBe(120.0);

    $allowed = $service->applyProgressUpdate($existing, 999999, 999999, allowSeekJump: true);

    expect($allowed['max_seconds'])->toBe(999999.0);
});

it('does not count a skip-to-end as fully watched', function () {
    $service = new LessonWatchProgressService();

    $skipped = $service->applyProgressUpdate([
        'percent' => 0,
        'max_seconds' => 5,
        'duration_seconds' => 100,
    ], 100, 100);

    expect($skipped['max_seconds'])->toBe(5.0)
        ->and($skipped['percent'])->toBe(5.0)
        ->and($skipped['percent'])->toBeLessThan(LessonWatchProgressService::COMPLETION_THRESHOLD);
});

it('allows rewind without lowering the farthest watched second', function () {
    $service = new LessonWatchProgressService();

    $rewound = $service->applyProgressUpdate([
        'percent' => 50,
        'max_seconds' => 60,
        'duration_seconds' => 120,
    ], 20, 120);

    expect($rewound['max_seconds'])->toBe(60.0)
        ->and($rewound['percent'])->toBe(50.0);
});
