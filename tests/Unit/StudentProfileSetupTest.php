<?php

use App\Models\ProfessionalType;
use App\Models\User;

function profileStudent(array $overrides = [], string $typeName = 'Architect'): User
{
    $user = new User(array_merge([
        'role' => 'student',
        'professional_type_id' => 1,
        'estimating_software' => ['Excel'],
        'construction_experience' => '1–3 Years',
        'worked_as_construction_va' => false,
    ], $overrides));

    $user->setRelation('professionalType', new ProfessionalType(['name' => $typeName]));

    return $user;
}

test('a student with the required background answers has a complete profile', function () {
    expect(profileStudent()->hasCompletedProfileSetup())->toBeTrue();
});

test('a blank referrer still counts as a complete profile', function () {
    $user = profileStudent([
        'referred_by' => null,
        'referrer_is_employee' => null,
    ]);

    expect($user->hasCompletedProfileSetup())->toBeTrue();
});

test('a referrer name requires the team answer', function () {
    $user = profileStudent([
        'referred_by' => 'Alex',
        'referrer_is_employee' => null,
    ]);

    expect($user->hasCompletedProfileSetup())->toBeFalse();

    $user->referrer_is_employee = true;

    expect($user->hasCompletedProfileSetup())->toBeTrue();
});

test('other professional type requires the written answer', function () {
    $user = profileStudent([
        'professional_type_other' => null,
    ], 'Other');

    expect($user->hasCompletedProfileSetup())->toBeFalse();

    $user->professional_type_other = 'Estimator';

    expect($user->hasCompletedProfileSetup())->toBeTrue();
});

test('missing software years or virtual assistant answer keeps the profile incomplete', function () {
    expect(profileStudent(['estimating_software' => []])->hasCompletedProfileSetup())->toBeFalse()
        ->and(profileStudent(['construction_experience' => null])->hasCompletedProfileSetup())->toBeFalse()
        ->and(profileStudent(['worked_as_construction_va' => null])->hasCompletedProfileSetup())->toBeFalse();
});
