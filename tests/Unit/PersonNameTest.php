<?php

use App\Models\Course\Course;
use App\Models\Course\CourseCertificate;
use App\Models\User;
use App\Services\Course\CourseCertificateIssuanceService;
use App\Services\StudentService;
use App\Support\PersonName;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

test('a multi-word name and a one-word name round trip to the same full name', function () {
    [$given, $last] = PersonName::split('  Alexis   Pacapac Test 1  ');

    expect($given)->toBe('Alexis')
        ->and($last)->toBe('Pacapac Test 1')
        ->and(PersonName::join($given, $last))->toBe('Alexis Pacapac Test 1');

    [$single, $rest] = PersonName::split('Madonna');

    expect($single)->toBe('Madonna')
        ->and($rest)->toBe('')
        ->and(PersonName::join($single, $rest))->toBe('Madonna');
});

test('the migration copies existing names once and does not split an edited name again', function () {
    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('role')->default('student');
        $table->timestamps();
    });

    DB::table('users')->insert([
        [
            'name' => '  Alexis   Pacapac Test 1  ',
            'email' => 'alexis@example.com',
            'password' => 'secret',
            'role' => 'student',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'name' => 'Madonna',
            'email' => 'madonna@example.com',
            'password' => 'secret',
            'role' => 'student',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $migration = require database_path('migrations/2026_10_30_000005_add_user_given_and_last_name.php');
    $migration->up();

    $alexis = DB::table('users')->where('email', 'alexis@example.com')->first();
    $madonna = DB::table('users')->where('email', 'madonna@example.com')->first();

    expect($alexis->name)->toBe('Alexis Pacapac Test 1')
        ->and($alexis->given_name)->toBe('Alexis')
        ->and($alexis->last_name)->toBe('Pacapac Test 1')
        ->and($alexis->email)->toBe('alexis@example.com')
        ->and($madonna->name)->toBe('Madonna')
        ->and($madonna->given_name)->toBe('Madonna')
        ->and($madonna->last_name)->toBeNull();

    DB::table('users')->where('email', 'alexis@example.com')->update([
        'given_name' => 'Alex',
        'last_name' => 'P',
        'name' => 'Alex P',
    ]);

    $migration->up();

    $edited = DB::table('users')->where('email', 'alexis@example.com')->first();

    expect($edited->given_name)->toBe('Alex')
        ->and($edited->last_name)->toBe('P')
        ->and($edited->name)->toBe('Alex P');
});

test('a later profile save keeps a name that was already split', function () {
    Schema::dropIfExists('media');
    Schema::dropIfExists('users');
    Schema::create('media', function (Blueprint $table) {
        $table->id();
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->string('collection_name');
        $table->string('name');
        $table->string('file_name');
        $table->string('disk');
        $table->unsignedBigInteger('size')->default(0);
        $table->json('manipulations');
        $table->json('custom_properties');
        $table->json('generated_conversions');
        $table->json('responsive_images');
        $table->nullableTimestamps();
    });
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('given_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('role')->default('student');
        $table->string('user_type')->nullable();
        $table->unsignedTinyInteger('status')->default(1);
        $table->timestamps();
    });

    $user = User::query()->create([
        'name' => 'Jane Ann Doe',
        'given_name' => 'Jane',
        'last_name' => 'Ann Doe',
        'email' => 'jane-profile@example.com',
        'password' => Hash::make('password'),
        'role' => 'student',
        'status' => 1,
    ]);

    $service = app(StudentService::class);
    $edited = $service->updateProfile([
        'given_name' => 'Janet',
        'last_name' => 'Doe',
    ], (string) $user->id);
    $untouched = $service->updateProfile([], (string) $user->id);

    expect($edited->name)->toBe('Janet Doe')
        ->and($edited->given_name)->toBe('Janet')
        ->and($edited->last_name)->toBe('Doe')
        ->and($untouched->name)->toBe('Janet Doe')
        ->and($untouched->given_name)->toBe('Janet')
        ->and($untouched->last_name)->toBe('Doe');
});

test('a certificate still uses the joined full name', function () {
    Schema::dropIfExists('course_certificates');
    Schema::dropIfExists('exam_attempts');
    Schema::dropIfExists('media');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('given_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('role')->default('student');
        $table->timestamps();
    });

    Schema::create('course_certificates', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->string('identifier')->nullable();
        $table->string('certificate_id')->nullable();
        $table->string('verification_reference')->nullable();
        $table->timestamps();
    });

    Schema::create('exam_attempts', function (Blueprint $table) {
        $table->id();
        $table->string('certificate_id')->nullable();
        $table->timestamps();
    });

    Schema::create('media', function (Blueprint $table) {
        $table->id();
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->uuid('uuid')->nullable();
        $table->string('collection_name');
        $table->string('name');
        $table->string('file_name');
        $table->string('mime_type')->nullable();
        $table->string('disk');
        $table->unsignedBigInteger('size');
        $table->json('manipulations');
        $table->json('custom_properties');
        $table->json('generated_conversions');
        $table->json('responsive_images');
        $table->unsignedInteger('order_column')->nullable();
        $table->nullableTimestamps();
    });

    $user = User::query()->create([
        'name' => 'Jane Ann Doe',
        'given_name' => 'Jane',
        'last_name' => 'Ann Doe',
        'email' => 'jane-certificate@example.com',
        'password' => Hash::make('password'),
        'role' => 'student',
    ]);

    foreach (['cv_resume' => 'resume.pdf', 'id_document' => 'id.pdf'] as $collection => $fileName) {
        $user->media()->create([
            'collection_name' => $collection,
            'name' => $collection,
            'file_name' => $fileName,
            'mime_type' => 'application/pdf',
            'disk' => 'public',
            'size' => 120,
            'manipulations' => [],
            'custom_properties' => ['name' => $collection],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);
    }

    $course = new Course;
    $course->id = 15;
    $course->exists = true;

    $certificate = app(CourseCertificateIssuanceService::class)->issueForCourse($course, $user->fresh());

    expect($certificate)->not->toBeNull()
        ->and($certificate->user_id)->toBe($user->id)
        ->and(User::query()->find($user->id)?->name)->toBe('Jane Ann Doe')
        ->and(CourseCertificate::query()->count())->toBe(1);
});
