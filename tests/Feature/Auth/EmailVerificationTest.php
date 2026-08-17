<?php

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get('/verify-email');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('auth/verify-email')
        ->where('email', $user->email)
    );
});

test('email can be verified with a code then continues to the student agreement', function () {
    $user = User::factory()->unverified()->create();
    $user->forceFill([
        'email_verification_code_hash' => Hash::make('123456'),
        'email_verification_expires_at' => now()->addMinutes(15),
        'email_verification_attempts' => 0,
    ])->save();

    Event::fake();

    $response = $this->actingAs($user)->post(route('verification.verify'), [
        'code' => '123456',
    ]);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('legal.agreement.show', absolute: false));
});

test('verified users who already accepted the agreement go to the dashboard', function () {
    $user = User::factory()->unverified()->create([
        'legal_agreement_accepted_at' => now(),
        'legal_agreement_version' => config('legal.agreement_version', '2026-07-16'),
    ]);
    $user->forceFill([
        'email_verification_code_hash' => Hash::make('654321'),
        'email_verification_expires_at' => now()->addMinutes(15),
        'email_verification_attempts' => 0,
    ])->save();

    Event::fake();

    $response = $this->actingAs($user)->post(route('verification.verify'), [
        'code' => '654321',
    ]);

    Event::assertDispatched(Verified::class);
    $response->assertRedirect(route('dashboard.external', ['tab' => 'home'], false).'?verified=1');
});

test('email is not verified with an incorrect code', function () {
    $user = User::factory()->unverified()->create();
    $user->forceFill([
        'email_verification_code_hash' => Hash::make('123456'),
        'email_verification_expires_at' => now()->addMinutes(15),
        'email_verification_attempts' => 0,
    ])->save();

    $response = $this->actingAs($user)->post(route('verification.verify'), [
        'code' => '000000',
    ]);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    $response->assertSessionHasErrors('code');
});

test('expired verification codes must be requested again', function () {
    $user = User::factory()->unverified()->create();
    $user->forceFill([
        'email_verification_code_hash' => Hash::make('123456'),
        'email_verification_expires_at' => now()->subMinute(),
        'email_verification_attempts' => 0,
    ])->save();

    $response = $this->actingAs($user)->post(route('verification.verify'), [
        'code' => '123456',
    ]);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    $response->assertSessionHasErrors('code');
});

test('old verification links send the student to enter a code', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get('/verify-email/'.$user->id.'/'.sha1($user->email));

    $response->assertRedirect(route('verification.notice', absolute: false));
    $response->assertSessionHas('error');
});

test('unverified users cannot open the dashboard', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('dashboard.external', ['tab' => 'home']));

    $response->assertRedirect(route('verification.notice', absolute: false));
});

test('unverified users are sent to verify email after login', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('verification.notice', absolute: false));
});

test('a new verification code can be resent', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->post(route('verification.send'));

    $response->assertRedirect();
    $response->assertSessionHas('status', 'verification-code-sent');
    Notification::assertSentTo($user, VerifyEmailNotification::class);
});
