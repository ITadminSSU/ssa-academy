<?php

use App\Http\Middleware\CheckEnroll;
use App\Models\Course\Course;
use App\Models\Course\CourseCertificate;
use App\Models\Course\WatchHistory;
use App\Models\ProfessionalType;
use App\Models\User;
use App\Services\Course\CourseCertificateIssuanceService;
use App\Services\Course\CourseEnrollmentService;
use App\Services\StudentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::disableForeignKeyConstraints();

    Schema::dropIfExists('media');
    Schema::dropIfExists('course_certificates');
    Schema::dropIfExists('exam_attempts');
    Schema::dropIfExists('watch_histories');
    Schema::dropIfExists('courses');
    Schema::dropIfExists('professional_types');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('role')->default('student');
        $table->string('user_type')->nullable();
        $table->unsignedTinyInteger('status')->default(1);
        $table->string('candidate_status')->nullable();
        $table->unsignedBigInteger('professional_type_id')->nullable();
        $table->string('professional_type_other')->nullable();
        $table->text('estimating_software')->nullable();
        $table->string('estimating_software_other')->nullable();
        $table->string('construction_experience')->nullable();
        $table->boolean('worked_as_construction_va')->nullable();
        $table->string('referred_by')->nullable();
        $table->boolean('referrer_is_employee')->nullable();
        $table->timestamp('legal_agreement_accepted_at')->nullable();
        $table->string('legal_agreement_version')->nullable();
        $table->timestamps();
    });

    Schema::create('professional_types', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedInteger('sort_order')->default(0);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });

    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('slug')->nullable();
        $table->timestamps();
    });

    Schema::create('watch_histories', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->string('current_watching_id')->nullable();
        $table->string('current_watching_type')->nullable();
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
        $table->string('conversions_disk')->nullable();
        $table->unsignedBigInteger('size');
        $table->json('manipulations');
        $table->json('custom_properties');
        $table->json('generated_conversions');
        $table->json('responsive_images');
        $table->unsignedInteger('order_column')->nullable()->index();
        $table->nullableTimestamps();
    });

    Schema::enableForeignKeyConstraints();
});

function signupStudent(): User
{
    return User::create([
        'name' => 'New Student',
        'email' => 'student-'.uniqid().'@example.com',
        'password' => Hash::make('password'),
        'role' => 'student',
        'user_type' => 'external',
        'status' => 1,
    ]);
}

test('profile answers save on the existing columns and clear the unfinished state', function () {
    $user = signupStudent();
    $type = ProfessionalType::create([
        'name' => 'Architect',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $saved = app(StudentService::class)->updateProfile([
        'name' => 'New Student',
        'professional_type_id' => $type->id,
        'estimating_software' => ['Excel'],
        'construction_experience' => '1–3 Years',
        'worked_as_construction_va' => false,
        'referred_by' => '',
    ], (string) $user->id);

    expect($saved->hasCompletedProfileSetup())->toBeTrue()
        ->and($saved->estimating_software)->toBe(['Excel'])
        ->and($saved->worked_as_construction_va)->toBeFalse()
        ->and($saved->referred_by)->toBeNull();
});

test('enrolling before the agreement does not start the course', function () {
    Schema::table('courses', function (Blueprint $table) {
        $table->string('status')->nullable();
        $table->timestamp('launch_at')->nullable();
        $table->string('expiry_type')->nullable();
        $table->string('expiry_duration')->nullable();
    });

    Schema::create('course_enrollments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->string('enrollment_type')->nullable();
        $table->string('access_status')->nullable();
        $table->timestamp('entry_date')->nullable();
        $table->timestamp('expiry_date')->nullable();
        $table->timestamps();
    });

    Schema::create('section_lessons', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('course_id');
        $table->timestamps();
    });

    $user = signupStudent();
    $courseId = \Illuminate\Support\Facades\DB::table('courses')->insertGetId([
        'title' => 'Estimating',
        'slug' => 'estimating',
        'status' => 'approved',
        'expiry_type' => 'lifetime',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    \Illuminate\Support\Facades\DB::table('section_lessons')->insert([
        'course_id' => $courseId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $enrollment = app(CourseEnrollmentService::class)->createCourseEnroll([
        'user_id' => $user->id,
        'course_id' => $courseId,
        'enrollment_type' => 'free',
    ], false, false);

    expect($enrollment->exists)->toBeTrue()
        ->and(WatchHistory::query()->count())->toBe(0);
});

test('the first course start asks for the agreement and does not open the player', function () {
    $user = signupStudent();
    $request = Request::create('/player/init/watch-history', 'POST', ['course_id' => 42]);
    $session = app('session')->driver();
    $session->start();
    $request->setLaravelSession($session);
    Auth::setUser($user);

    $response = app(CheckEnroll::class)->handle($request, fn () => response('opened'));

    expect($response->isRedirect())->toBeTrue()
        ->and($response->headers->get('Location'))->toContain('legal/agreement')
        ->and($session->get('pending_course_start_id'))->toBe(42)
        ->and(WatchHistory::query()->count())->toBe(0);
});

test('a course that already has watch history is not sent to the agreement', function () {
    $user = signupStudent();
    WatchHistory::query()->create([
        'user_id' => $user->id,
        'course_id' => 42,
        'current_watching_id' => '1',
        'current_watching_type' => 'lesson',
    ]);

    $request = Request::create('/player/init/watch-history', 'POST', ['course_id' => 42]);
    $session = app('session')->driver();
    $session->start();
    $request->setLaravelSession($session);
    Auth::setUser($user);

    $response = app(CheckEnroll::class)->handle($request, fn () => response('opened'));

    expect($response->headers->get('Location'))->not->toContain('legal/agreement');
});

test('a new certificate waits for documents and an issued certificate stays available', function () {
    $user = signupStudent();
    $course = new Course;
    $course->id = 9;
    $course->exists = true;
    $service = app(CourseCertificateIssuanceService::class);

    expect($service->issueForCourse($course, $user))->toBeNull()
        ->and(CourseCertificate::query()->count())->toBe(0);

    $existing = CourseCertificate::query()->create([
        'user_id' => $user->id,
        'course_id' => 9,
        'identifier' => 'SSU-VRN-EXISTING123',
        'certificate_id' => 'SSU-2026-00012',
        'verification_reference' => 'SSU-VRN-EXISTING123',
    ]);

    $again = $service->issueForCourse($course, $user->fresh());

    expect($again?->id)->toBe($existing->id)
        ->and($again?->certificate_id)->toBe('SSU-2026-00012');
});

test('one cv and id on the account releases certificates for later courses', function () {
    $user = signupStudent();

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

    $first = new Course;
    $first->id = 3;
    $first->exists = true;
    $second = new Course;
    $second->id = 4;
    $second->exists = true;

    $service = app(CourseCertificateIssuanceService::class);
    $issuedFirst = $service->issueForCourse($first, $user->fresh());
    $issuedSecond = $service->issueForCourse($second, $user->fresh());

    expect($issuedFirst)->not->toBeNull()
        ->and($issuedSecond)->not->toBeNull()
        ->and($issuedFirst->id)->not->toBe($issuedSecond->id)
        ->and($user->fresh()->getMedia('cv_resume'))->toHaveCount(1)
        ->and($user->fresh()->getMedia('id_document'))->toHaveCount(1);
});
