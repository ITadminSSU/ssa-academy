<?php

use App\Models\Page;
use App\Models\ProfessionalType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    Storage::fake('public');

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

    Page::create([
        'name' => 'Non-Disclosure Agreement',
        'slug' => 'non-disclosure-agreement',
        'type' => 'inner_page',
        'title' => 'Non-Disclosure Agreement (NDA)',
        'description' => '<p>NDA content</p>',
        'active' => true,
    ]);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'recaptcha_status' => false,
        'professional_type_id' => $professionalType->id,
        'cv_resume' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
        'accept_terms' => true,
        'accept_nda' => true,
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});