<?php

use App\Services\TeamMemberService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('team_members');

    Schema::create('team_members', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('role');
        $table->text('short_description')->nullable();
        $table->string('photo')->nullable();
        $table->unsignedInteger('sort_order')->default(0);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('team_members');
});

test('active team members keep a short description for the public pages', function () {
    $service = new TeamMemberService();

    $service->create([
        'name' => 'Ava Santos',
        'role' => 'Estimator',
        'short_description' => 'Leads commercial takeoff reviews.',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $service->create([
        'name' => 'Hidden Person',
        'role' => 'Trainer',
        'short_description' => 'Should not appear.',
        'sort_order' => 2,
        'is_active' => false,
    ]);

    $public = $service->listForPublic();

    expect($public)->toHaveCount(1)
        ->and($public->first()->name)->toBe('Ava Santos')
        ->and($public->first()->short_description)->toBe('Leads commercial takeoff reviews.');
});

test('a blank short description is stored as empty', function () {
    $member = (new TeamMemberService())->create([
        'name' => 'Ava Santos',
        'role' => 'Estimator',
        'short_description' => '   ',
        'is_active' => true,
    ]);

    expect($member->short_description)->toBeNull();
});
