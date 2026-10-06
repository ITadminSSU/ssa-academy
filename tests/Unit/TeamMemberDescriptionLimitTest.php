<?php

use App\Http\Requests\TeamMemberRequest;
use App\Models\TeamMember;
use App\Rules\MaxWords;
use Illuminate\Support\Facades\Validator;

it('counts whitespace-separated tokens as words', function () {
    expect(MaxWords::count(''))->toBe(0)
        ->and(MaxWords::count('   '))->toBe(0)
        ->and(MaxWords::count("Experience in estimating.\n\nHe later pursued civil engineering."))->toBe(8);
});

it('allows a team member description of 1000 words', function () {
    $description = implode(' ', array_fill(0, TeamMember::DESCRIPTION_MAX_WORDS, 'civil'));

    $validator = Validator::make(
        ['short_description' => $description],
        ['short_description' => ['nullable', 'string', 'max:65535', new MaxWords(TeamMember::DESCRIPTION_MAX_WORDS)]],
    );

    expect(MaxWords::count($description))->toBe(1000)
        ->and($validator->passes())->toBeTrue();
});

it('allows descriptions longer than 500 characters when they stay within 1000 words', function () {
    $description = trim(str_repeat('Experience in estimating, project monitoring, and preparing construction plans. ', 8));

    expect(strlen($description))->toBeGreaterThan(500)
        ->and(MaxWords::count($description))->toBeLessThan(TeamMember::DESCRIPTION_MAX_WORDS);

    $validator = Validator::make(
        ['short_description' => $description],
        ['short_description' => ['nullable', 'string', 'max:65535', new MaxWords(TeamMember::DESCRIPTION_MAX_WORDS)]],
    );

    expect($validator->passes())->toBeTrue();
});

it('rejects a team member description over 1000 words', function () {
    $description = implode(' ', array_fill(0, TeamMember::DESCRIPTION_MAX_WORDS + 1, 'civil'));

    $validator = Validator::make(
        ['short_description' => $description],
        ['short_description' => ['nullable', 'string', 'max:65535', new MaxWords(TeamMember::DESCRIPTION_MAX_WORDS)]],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('short_description'))->toBe('The short description field must not be greater than 1000 words.');
});

it('labels the team member description field as description in validation errors', function () {
    $request = TeamMemberRequest::create('/dashboard/admin/settings/team-members', 'POST', [
        'name' => 'Ava Santos',
        'role' => 'Estimator',
        'short_description' => implode(' ', array_fill(0, TeamMember::DESCRIPTION_MAX_WORDS + 1, 'civil')),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $validator = Validator::make(
        $request->all(),
        $request->rules(),
        $request->messages(),
        $request->attributes(),
    );

    expect($validator->errors()->first('short_description'))->toBe('The description field must not be greater than 1000 words.');
});

it('validates team member descriptions by word count instead of 500 characters', function () {
    $request = new TeamMemberRequest;
    $rules = $request->rules()['short_description'];

    expect($rules)->toBeArray()
        ->and($rules)->toContain('nullable')
        ->and($rules)->not->toContain('max:500')
        ->and(collect($rules)->contains(fn ($rule) => $rule instanceof MaxWords))->toBeTrue();
});
