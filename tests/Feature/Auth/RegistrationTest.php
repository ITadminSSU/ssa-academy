<?php

use App\Models\Page;
use App\Models\ProfessionalType;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register and must verify email', function () {
    Storage::fake('public');
    Notification::fake();

    $professionalType = ProfessionalType::create([
        'name' => 'Architect',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    Page::create([
        'name' => 'Terms and Conditions',
        'slug' => 'terms-and-conditions',
        'type' => 'inner_page',
        'title' => 'Terms and Conditions',
        'description' => '<p>Terms content</p>',
        'active' => true,
    ]);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'recaptcha_status' => false,
        'professional_type_id' => $professionalType->id,
        'estimating_software' => ['Excel', 'Bluebeam Revu'],
        'construction_experience' => '1–3 Years',
        'worked_as_construction_va' => false,
        'cv_resume' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
        'id_document' => UploadedFile::fake()->image('id-card.jpg'),
        'accept_terms' => true,
        'accept_legal_age' => true,
        'accept_single_account' => true,
        'accept_student_integrity' => true,
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('verification.notice', absolute: false));

    $user = User::query()->where('email', 'test@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->hasMedia('cv_resume'))->toBeTrue()
        ->and($user->hasMedia('id_document'))->toBeTrue();

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

test('registration requires a government id upload', function () {
    Storage::fake('public');
    Notification::fake();

    $professionalType = ProfessionalType::create([
        'name' => 'Architect',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    Page::create([
        'name' => 'Terms and Conditions',
        'slug' => 'terms-and-conditions',
        'type' => 'inner_page',
        'title' => 'Terms and Conditions',
        'description' => '<p>Terms content</p>',
        'active' => true,
    ]);

    $response = $this->from('/register')->post('/register', [
        'name' => 'Test User',
        'email' => 'noid@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'recaptcha_status' => false,
        'professional_type_id' => $professionalType->id,
        'estimating_software' => ['Excel'],
        'construction_experience' => '1–3 Years',
        'worked_as_construction_va' => false,
        'cv_resume' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
        'accept_terms' => true,
        'accept_legal_age' => true,
        'accept_single_account' => true,
        'accept_student_integrity' => true,
    ]);

    $response->assertRedirect('/register');
    $response->assertSessionHasErrors('id_document');
    $this->assertGuest();
    expect(User::query()->where('email', 'noid@example.com')->exists())->toBeFalse();
});
