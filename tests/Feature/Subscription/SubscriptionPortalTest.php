<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires authentication for the billing portal route', function () {
    $this->get(route('subscriptions.portal'))
        ->assertRedirect();
});

it('blocks billing portal when the learner has no subscription history', function () {
    $user = User::factory()->create([
        'role' => 'student',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('subscriptions.portal'))
        ->assertRedirect()
        ->assertSessionHas('error');
});
