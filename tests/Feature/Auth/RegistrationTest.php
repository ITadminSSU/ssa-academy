<?php

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register with name email and password and must verify email', function () {
    Notification::fake();

    $response = $this->post('/register', [
        'given_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'recaptcha_status' => false,
        'accept_terms' => true,
        'accept_legal_age' => true,
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('verification.notice', absolute: false));

    $user = User::query()->where('email', 'test@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Test User')
        ->and($user->given_name)->toBe('Test')
        ->and($user->last_name)->toBe('User')
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->legal_agreement_accepted_at)->toBeNull()
        ->and($user->professional_type_id)->toBeNull()
        ->and($user->estimating_software)->toBeNull()
        ->and($user->hasMedia('cv_resume'))->toBeFalse()
        ->and($user->hasMedia('id_document'))->toBeFalse()
        ->and($user->hasCompletedProfileSetup())->toBeFalse();

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

test('registration does not require a cv or government id', function () {
    Notification::fake();

    $response = $this->post('/register', [
        'given_name' => 'Test',
        'last_name' => 'User',
        'email' => 'noid@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'recaptcha_status' => false,
        'accept_terms' => true,
        'accept_legal_age' => true,
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('verification.notice', absolute: false));
    expect(User::query()->where('email', 'noid@example.com')->exists())->toBeTrue();
});
