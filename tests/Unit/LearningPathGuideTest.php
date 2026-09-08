<?php

use App\Models\Course\Course;
use App\Support\LearningPathGuide;

it('matches PlanSwift fundamentals and advanced by title words', function () {
    $guide = new LearningPathGuide();

    expect($guide->titleContainsAll('PlanSwift Fundamentals', ['planswift', 'fundamental']))->toBeTrue()
        ->and($guide->titleContainsAll('Advanced PlanSwift', ['planswift', 'advanced']))->toBeTrue()
        ->and($guide->titleContainsAll('Advanced PlanSwift', ['planswift', 'fundamental']))->toBeFalse();
});

it('picks the first catalog course whose title contains all needles', function () {
    $guide = new LearningPathGuide();
    $fundamentals = new Course(['title' => 'PlanSwift Fundamentals', 'slug' => 'planswift-fundamentals']);
    $advanced = new Course(['title' => 'Advanced PlanSwift', 'slug' => 'advanced-planswift']);

    expect($guide->firstMatchingCourse(collect([$advanced, $fundamentals]), ['planswift', 'fundamental'])->title)
        ->toBe('PlanSwift Fundamentals');
});
