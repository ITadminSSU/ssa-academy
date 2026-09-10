<?php

use App\Services\Course\QuizTakeoffService;
use Illuminate\Validation\ValidationException;

it('allows a single takeoff question on an empty quiz', function () {
    $service = app(QuizTakeoffService::class);

    $service->assertQuestionMix([], ['quantity_takeoff']);

    expect(true)->toBeTrue();
});

it('rejects mixing takeoff with other question types', function () {
    $service = app(QuizTakeoffService::class);

    $service->assertQuestionMix([], ['single', 'quantity_takeoff']);
})->throws(ValidationException::class);

it('rejects adding takeoff to a quiz that already has questions', function () {
    $service = app(QuizTakeoffService::class);

    $service->assertQuestionMix(['single'], ['quantity_takeoff']);
})->throws(ValidationException::class);

it('rejects adding knowledge-check questions to a takeoff quiz', function () {
    $service = app(QuizTakeoffService::class);

    $service->assertQuestionMix(['quantity_takeoff'], ['single']);
})->throws(ValidationException::class);

it('still allows mixing single multiple and true false on a regular quiz', function () {
    $service = app(QuizTakeoffService::class);

    $service->assertQuestionMix(['single'], ['multiple', 'boolean']);

    expect(true)->toBeTrue();
});
