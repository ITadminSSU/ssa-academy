<?php

use App\Http\Controllers\Admin\StudentLabelController;
use App\Http\Requests\SyncStudentLabelsRequest;
use App\Models\Course\CourseEnrollment;
use App\Models\StudentLabel;
use App\Models\User;
use App\Services\StudentLabelService;
use App\Services\UserService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('course_enrollments');
    Schema::dropIfExists('media');
    Schema::dropIfExists('instructors');
    Schema::dropIfExists('student_label_user');
    Schema::dropIfExists('student_labels');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('given_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('role')->nullable();
        $table->string('user_type')->nullable();
        $table->boolean('pef_tagged')->default(false);
        $table->unsignedTinyInteger('status')->default(1);
        $table->unsignedBigInteger('instructor_id')->nullable();
        $table->string('candidate_status')->nullable();
        $table->timestamps();
    });

    Schema::create('student_labels', function (Blueprint $table) {
        $table->id();
        $table->string('name', 40);
        $table->string('color', 7);
        $table->timestamps();
    });

    Schema::create('student_label_user', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('student_label_id');
        $table->timestamps();
    });

    Schema::create('instructors', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('media', function (Blueprint $table) {
        $table->id();
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->uuid()->nullable();
        $table->string('collection_name');
        $table->string('name');
        $table->string('file_name');
        $table->string('mime_type')->nullable();
        $table->string('disk');
        $table->string('conversions_disk')->nullable();
        $table->unsignedBigInteger('size')->default(0);
        $table->json('manipulations')->nullable();
        $table->json('custom_properties')->nullable();
        $table->json('generated_conversions')->nullable();
        $table->json('responsive_images')->nullable();
        $table->unsignedInteger('order_column')->nullable();
        $table->timestamps();
    });

    Schema::create('course_enrollments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->string('enrollment_type')->default('paid');
        $table->string('access_status')->default('active');
        $table->timestamps();
    });
});

afterEach(function () {
    Auth::logout();
    Schema::dropIfExists('course_enrollments');
    Schema::dropIfExists('media');
    Schema::dropIfExists('instructors');
    Schema::dropIfExists('student_label_user');
    Schema::dropIfExists('student_labels');
    Schema::dropIfExists('users');
});

test('saving a label from the enrollment action stores it and does not change pef', function () {
    $admin = tagUser('Admin', 'admin@example.com', 'admin');
    $student = tagUser('Ada Student', 'ada@example.com', 'student');
    $student->forceFill(['pef_tagged' => false])->save();
    $label = StudentLabel::query()->create(['name' => 'EVT', 'color' => '#2563eb']);
    CourseEnrollment::query()->create([
        'user_id' => $student->id,
        'course_id' => 10,
        'enrollment_type' => 'paid',
        'access_status' => 'active',
    ]);
    CourseEnrollment::query()->create([
        'user_id' => $student->id,
        'course_id' => 11,
        'enrollment_type' => 'paid',
        'access_status' => 'active',
    ]);

    saveEnrollmentTags($admin, $student, [$label->id]);

    $rows = CourseEnrollment::query()->with('user.studentLabels')->orderBy('course_id')->get();

    expect($student->fresh()->pef_tagged)->toBeFalse()
        ->and($rows)->toHaveCount(2)
        ->and($rows[0]->user->studentLabels->pluck('name')->all())->toBe(['EVT'])
        ->and($rows[1]->user->studentLabels->pluck('name')->all())->toBe(['EVT']);
});

test('saving with no tags selected removes the tags', function () {
    $admin = tagUser('Admin', 'admin@example.com', 'admin');
    $student = tagUser('Ada Student', 'ada@example.com', 'student');
    $label = StudentLabel::query()->create(['name' => 'EVT', 'color' => '#2563eb']);
    $student->studentLabels()->attach($label->id);

    saveEnrollmentTags($admin, $student, []);

    expect($student->studentLabels()->count())->toBe(0)
        ->and($student->fresh()->pef_tagged)->toBeFalse();
});

test('saving the user form without student label ids leaves the tags in place', function () {
    $student = tagUser('Ada Student', 'ada@example.com', 'student', [
        'given_name' => 'Ada',
        'last_name' => 'Student',
        'status' => 1,
    ]);
    $label = StudentLabel::query()->create(['name' => 'EVT', 'color' => '#2563eb']);
    $student->studentLabels()->attach($label->id);

    app(UserService::class)->updateUser($student->id, [
        'given_name' => 'Ada',
        'last_name' => 'Student',
        'email' => $student->email,
        'status' => 1,
        'user_type' => 'external',
    ]);

    expect($student->studentLabels()->pluck('name')->all())->toBe(['EVT']);
});

test('an instructor cannot save tags', function () {
    $instructor = tagUser('Trainer', 'trainer@example.com', 'instructor');
    $student = tagUser('Ada Student', 'ada@example.com', 'student');
    $label = StudentLabel::query()->create(['name' => 'EVT', 'color' => '#2563eb']);

    expect(fn () => saveEnrollmentTags($instructor, $student, [$label->id]))
        ->toThrow(AuthorizationException::class);

    expect($student->studentLabels()->count())->toBe(0)
        ->and(app('router')->getRoutes()->getByName('enrollments.student-labels.sync')->middleware())
        ->toContain('role:admin');
});

test('the enrollment tag control confirms before it saves', function () {
    $page = file_get_contents(base_path('resources/js/pages/dashboard/enrollments/partials/enrollment-student-tag-editor.tsx'));

    expect($page)->toContain('Save this tag change for this student?')
        ->and($page)->toContain('Choose tags')
        ->and($page)->toContain('rounded-full')
        ->and($page)->toContain('enrollments.student-labels.sync')
        ->and($page)->not->toContain('min-h-10')
        ->and($page)->not->toContain('w-full')
        ->and($page)->not->toContain('>Save<');
});

function tagUser(string $name, string $email, string $role, array $extra = []): User
{
    return User::query()->create(array_merge([
        'name' => $name,
        'email' => $email,
        'role' => $role,
        'user_type' => 'external',
        'pef_tagged' => false,
        'status' => 1,
    ], $extra));
}

function saveEnrollmentTags(User $actor, User $student, array $labelIds): void
{
    Auth::login($actor);

    $request = SyncStudentLabelsRequest::create(
        '/dashboard/admin/enrollments/students/'.$student->id.'/labels',
        'PUT',
        ['student_label_ids' => $labelIds],
    );
    $request->headers->set('referer', '/dashboard/trainer/enrollments/courses');
    $request->setLaravelSession(app('session.store'));
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setUserResolver(fn () => $actor);
    $request->validateResolved();

    app(StudentLabelController::class)->syncStudent($request, $student, app(StudentLabelService::class));
}
