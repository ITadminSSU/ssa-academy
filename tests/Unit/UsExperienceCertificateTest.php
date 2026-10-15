<?php

use App\Http\Controllers\CertificateVerificationController;
use App\Models\Course\Course;
use App\Models\Course\CourseCertificate;
use App\Models\Course\UsExperienceAttempt;
use App\Models\Course\UsExperienceCertificate;
use App\Models\Course\UsExperiencePlan;
use App\Models\User;
use App\Services\UsExperience\UsExperienceCertificateIssuanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    foreach ([
        'us_experience_certificates',
        'us_experience_attempts',
        'us_experience_plans',
        'course_certificates',
        'exam_attempts',
        'courses',
        'users',
    ] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('role')->nullable();
        $table->string('user_type')->nullable();
        $table->string('candidate_status')->nullable();
        $table->timestamps();
    });

    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('slug')->nullable();
        $table->timestamps();
    });

    Schema::create('course_certificates', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->string('identifier')->unique();
        $table->string('certificate_id')->nullable()->unique();
        $table->string('verification_reference')->nullable()->unique();
        $table->timestamps();
    });

    Schema::create('exam_attempts', function (Blueprint $table) {
        $table->id();
        $table->string('certificate_id')->nullable();
        $table->string('tracking_reference')->nullable();
        $table->timestamps();
    });

    Schema::create('us_experience_plans', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('course_id');
        $table->string('group_name');
        $table->string('title');
        $table->unsignedInteger('sort_order')->default(0);
        $table->unsignedInteger('pass_mark')->default(85);
        $table->unsignedInteger('max_attempts')->default(10);
        $table->boolean('published')->default(false);
        $table->json('drawings')->nullable();
        $table->string('blank_template_file_url')->nullable();
        $table->json('line_items')->nullable();
        $table->timestamps();
    });

    Schema::create('us_experience_attempts', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('us_experience_plan_id');
        $table->unsignedBigInteger('user_id');
        $table->unsignedInteger('attempt_number');
        $table->string('takeoff_pdf_url');
        $table->string('boq_xlsx_url');
        $table->decimal('lines_percent', 8, 2)->nullable();
        $table->string('status');
        $table->timestamp('submitted_at')->nullable();
        $table->timestamps();
    });

    Schema::create('us_experience_certificates', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->string('group_name');
        $table->string('identifier')->unique();
        $table->string('certificate_id')->nullable()->unique();
        $table->string('verification_reference')->nullable()->unique();
        $table->timestamps();
        $table->unique(['user_id', 'course_id', 'group_name']);
    });
});

function experienceStudent(): User
{
    return User::create([
        'name' => 'Alexis Pacapac',
        'email' => 'student-'.uniqid().'@example.com',
        'password' => 'password',
        'role' => 'student',
        'user_type' => 'external',
    ]);
}

function experienceCourse(): Course
{
    return Course::create([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-'.uniqid(),
    ]);
}

function readyExperiencePlan(Course $course, string $group, string $title, int $sort = 1): UsExperiencePlan
{
    return UsExperiencePlan::create([
        'course_id' => $course->id,
        'group_name' => $group,
        'title' => $title,
        'sort_order' => $sort,
        'pass_mark' => 85,
        'max_attempts' => 10,
        'published' => true,
        'line_items' => [[
            'key' => 'wall',
            'item' => 'Wall',
            'unit' => 'SF',
            'expected_qty' => 10,
        ]],
        'blank_template_file_url' => 'https://example.test/blank.xlsx',
        'drawings' => [[
            'file_url' => 'https://example.test/plan.pdf',
            'name' => 'Plan',
        ]],
    ]);
}

function recordExperienceAttempt(UsExperiencePlan $plan, User $user, string $status, float $percent): void
{
    UsExperienceAttempt::create([
        'us_experience_plan_id' => $plan->id,
        'user_id' => $user->id,
        'attempt_number' => 1,
        'takeoff_pdf_url' => 'https://example.test/takeoff.pdf',
        'boq_xlsx_url' => 'https://example.test/boq.xlsx',
        'lines_percent' => $percent,
        'status' => $status,
        'submitted_at' => now(),
    ]);
}

function verificationProps(string $reference): array
{
    $request = Request::create('/verify-certificate/'.$reference, 'GET');
    $page = app(CertificateVerificationController::class)->show($request, $reference);
    $props = (new ReflectionClass($page))->getProperty('props');

    return $props->getValue($page);
}

it('issues one certificate only after every ready plan in the group is passed', function () {
    $student = experienceStudent();
    $course = experienceCourse();
    $first = readyExperiencePlan($course, 'Beginner', 'Plan A', 1);
    $second = readyExperiencePlan($course, 'Beginner', 'Plan B', 2);
    readyExperiencePlan($course, 'Advanced', 'Plan C', 3);
    $service = app(UsExperienceCertificateIssuanceService::class);

    expect($service->issueIfGroupPassed($student, $course, 'Beginner'))->toBeNull()
        ->and(UsExperienceCertificate::query()->count())->toBe(0);

    recordExperienceAttempt($first, $student, 'passed', 90);

    expect($service->issueIfGroupPassed($student, $course, 'Beginner'))->toBeNull();

    recordExperienceAttempt($second, $student, 'passed', 88);
    $issued = $service->issueIfGroupPassed($student, $course, 'Beginner');
    $again = $service->issueIfGroupPassed($student, $course, 'Beginner');

    expect($issued)->not->toBeNull()
        ->and($again?->id)->toBe($issued?->id)
        ->and($issued?->group_name)->toBe('Beginner')
        ->and($issued?->certificate_id)->toStartWith('SSU-'.now()->format('Y').'-')
        ->and($issued?->verification_reference)->toStartWith('SSU-VRN-')
        ->and(UsExperienceCertificate::query()->count())->toBe(1)
        ->and($service->forStudent($course, $student))->toHaveCount(1)
        ->and($service->issueIfGroupPassed($student, $course, 'Advanced'))->toBeNull();
});

it('does not issue a certificate for an empty group, a short score, or a plan that was only opened', function () {
    $student = experienceStudent();
    $course = experienceCourse();
    $draft = UsExperiencePlan::create([
        'course_id' => $course->id,
        'group_name' => 'Empty',
        'title' => 'Not ready',
        'published' => false,
        'pass_mark' => 85,
    ]);
    $short = readyExperiencePlan($course, 'Beginner', 'Short score');
    recordExperienceAttempt($short, $student, 'failed', 70);
    $service = app(UsExperienceCertificateIssuanceService::class);

    expect($service->issueIfGroupPassed($student, $course, 'Empty'))->toBeNull()
        ->and($service->issueIfGroupPassed($student, $course, 'Beginner'))->toBeNull()
        ->and($draft->isReady())->toBeFalse()
        ->and($service->forStudent($course, $student))->toBe([]);
});

it('keeps the next certificate id after existing course certificates', function () {
    $student = experienceStudent();
    $course = experienceCourse();
    $year = now()->format('Y');
    CourseCertificate::create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'identifier' => 'SSU-VRN-COURSEONLY1',
        'verification_reference' => 'SSU-VRN-COURSEONLY1',
        'certificate_id' => "SSU-{$year}-00012",
    ]);
    $plan = readyExperiencePlan($course, 'Beginner', 'Plan A');
    recordExperienceAttempt($plan, $student, 'passed', 100);

    $issued = app(UsExperienceCertificateIssuanceService::class)->issueIfGroupPassed($student, $course, 'Beginner');

    expect($issued?->certificate_id)->toBe("SSU-{$year}-00013")
        ->and(CourseCertificate::query()->count())->toBe(1);
});

it('verifies a US Experience certificate on the same lookup and leaves course verification unchanged', function () {
    $student = experienceStudent();
    $course = experienceCourse();
    $plan = readyExperiencePlan($course, 'Beginner', 'Plan A');
    recordExperienceAttempt($plan, $student, 'passed', 92);
    $issued = app(UsExperienceCertificateIssuanceService::class)->issueIfGroupPassed($student, $course, 'Beginner');

    $experience = verificationProps((string) $issued?->verification_reference);

    expect($experience['result']['valid'])->toBeTrue()
        ->and($experience['result']['type'])->toBe('us_experience')
        ->and($experience['result']['recipient_name'])->toBe('Alexis Pacapac')
        ->and($experience['result']['title'])->toBe('Build Your US Experience')
        ->and($experience['result']['course_title'])->toBe('Drywall Estimating')
        ->and($experience['result']['group_name'])->toBe('Beginner')
        ->and($experience['result']['issued_at'])->not->toBeEmpty();

    $courseCertificate = CourseCertificate::create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'identifier' => 'SSU-VRN-COURSEONLY2',
        'verification_reference' => 'SSU-VRN-COURSEONLY2',
        'certificate_id' => 'SSU-'.now()->format('Y').'-00020',
    ]);
    $courseResult = verificationProps($courseCertificate->verification_reference);

    expect($courseResult['result']['valid'])->toBeTrue()
        ->and($courseResult['result']['type'])->toBe('course')
        ->and($courseResult['result']['title'])->toBe('Drywall Estimating');

    $missing = verificationProps('SSU-VRN-DOESNOTEXIST');

    expect($missing['result']['valid'])->toBeFalse()
        ->and($missing['result']['reference'])->toBe('SSU-VRN-DOESNOTEXIST');
});
