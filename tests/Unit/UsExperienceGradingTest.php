<?php

use App\Models\Course\UsExperienceAttempt;
use App\Models\Course\UsExperiencePlan;
use App\Services\UsExperience\UsExperienceUnlockService;
use Modules\Exam\Services\QuantityTakeoffGradingService;

it('uses ±2 as the default absolute band when no per-line override is set', function () {
    $grader = new QuantityTakeoffGradingService();
    $key = [[
        'key' => 'item-a',
        'item' => 'Drywall',
        'unit' => 'SF',
        'expected_qty' => 5,
    ]];

    $pass = $grader->grade($key, ['quantities' => ['item-a' => 3]], 100, 2.0);
    $fail = $grader->grade($key, ['quantities' => ['item-a' => 8]], 100, 2.0);

    expect($pass['lines_correct'])->toBe(1);
    expect($pass['grading_breakdown'][0]['tolerance'])->toBe(2.0);
    expect($fail['lines_correct'])->toBe(0);
});

it('uses a trainer per-line override instead of the ±2 default', function () {
    $grader = new QuantityTakeoffGradingService();
    $key = [[
        'key' => 'item-a',
        'item' => 'Drywall',
        'unit' => 'SF',
        'expected_qty' => 5,
        'tolerance_override' => 0.5,
    ]];

    $inside = $grader->grade($key, ['quantities' => ['item-a' => 5.4]], 100, 2.0);
    $outside = $grader->grade($key, ['quantities' => ['item-a' => 3]], 100, 2.0);

    expect($inside['lines_correct'])->toBe(1);
    expect($inside['grading_breakdown'][0]['tolerance'])->toBe(0.5);
    expect($outside['lines_correct'])->toBe(0);
});

it('does not apply exam unit floors when an absolute default tolerance is provided', function () {
    $grader = new QuantityTakeoffGradingService();
    $key = [[
        'key' => 'item-a',
        'item' => 'Area',
        'unit' => 'SF',
        'expected_qty' => 100,
    ]];

    // Exam floors would allow ±50 SF. Plan default ±2 must fail 90.
    $result = $grader->grade($key, ['quantities' => ['item-a' => 90]], 100, 2.0);

    expect($result['lines_correct'])->toBe(0);
    expect($result['grading_breakdown'][0]['tolerance'])->toBe(2.0);
});

it('unlocks the next plan only after the previous plan is passed', function () {
    $service = app(UsExperienceUnlockService::class);

    $first = new UsExperiencePlan(['title' => 'Plan 1', 'group_name' => 'Skills', 'pass_mark' => 85, 'max_attempts' => 10]);
    $first->id = 1;
    $second = new UsExperiencePlan(['title' => 'Plan 2', 'group_name' => 'Skills', 'pass_mark' => 85, 'max_attempts' => 10]);
    $second->id = 2;

    $failed = new UsExperienceAttempt([
        'attempt_number' => 1,
        'status' => UsExperienceAttempt::STATUS_FAILED,
        'lines_percent' => 40,
    ]);

    $payloads = $service->studentPlanPayloads(
        collect([$first, $second]),
        collect([
            1 => collect([$failed]),
            2 => collect(),
        ]),
        true,
        true,
    );

    expect($payloads[0]['status'])->toBe('ongoing');
    expect($payloads[0]['unlocked'])->toBeTrue();
    expect($payloads[1]['status'])->toBe('locked');
    expect($payloads[1]['can_download'])->toBeFalse();
    expect($payloads[1]['can_submit'])->toBeFalse();
});

it('unlocks plan 2 after plan 1 is passed and keeps scores visible without file actions', function () {
    $service = app(UsExperienceUnlockService::class);

    $first = new UsExperiencePlan(['title' => 'Plan 1', 'group_name' => 'Skills', 'pass_mark' => 85, 'max_attempts' => 10]);
    $first->id = 1;
    $second = new UsExperiencePlan(['title' => 'Plan 2', 'group_name' => 'Skills', 'pass_mark' => 85, 'max_attempts' => 10]);
    $second->id = 2;

    $passed = new UsExperienceAttempt([
        'attempt_number' => 1,
        'status' => UsExperienceAttempt::STATUS_PASSED,
        'lines_percent' => 92,
    ]);

    $payloads = $service->studentPlanPayloads(
        collect([$first, $second]),
        collect([
            1 => collect([$passed]),
            2 => collect(),
        ]),
        false,
        true,
    );

    expect($payloads[0]['status'])->toBe('passed');
    expect($payloads[0]['accuracy'])->toBe(92.0);
    expect($payloads[0]['can_download'])->toBeFalse();
    expect($payloads[1]['unlocked'])->toBeTrue();
    expect($payloads[1]['can_submit'])->toBeFalse();
});
