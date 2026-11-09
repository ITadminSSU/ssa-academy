<?php

use App\Services\Course\CourseEnrollmentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('student_label_user');
    Schema::dropIfExists('student_labels');
    Schema::dropIfExists('course_enrollments');
    Schema::dropIfExists('courses');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->boolean('pef_tagged')->default(false);
        $table->timestamps();
    });

    Schema::create('student_labels', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('color');
        $table->timestamps();
    });

    Schema::create('student_label_user', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('student_label_id');
        $table->timestamps();
    });

    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->unsignedBigInteger('instructor_id')->nullable();
        $table->timestamps();
    });

    Schema::create('course_enrollments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->string('access_status')->nullable();
        $table->timestamp('balance_paid_at')->nullable();
        $table->timestamp('forfeited_at')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('student_label_user');
    Schema::dropIfExists('student_labels');
    Schema::dropIfExists('course_enrollments');
    Schema::dropIfExists('courses');
    Schema::dropIfExists('users');
    DB::connection()->setTablePrefix('');
});

function overviewFixture(): void
{
    $now = now();

    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Ada', 'pef_tagged' => 0, 'created_at' => $now, 'updated_at' => $now],
        ['id' => 2, 'name' => 'Bea', 'pef_tagged' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['id' => 3, 'name' => 'Cam', 'pef_tagged' => 0, 'created_at' => $now, 'updated_at' => $now],
        ['id' => 4, 'name' => 'Dee', 'pef_tagged' => 0, 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('courses')->insert([
        ['id' => 1, 'title' => 'Alpha', 'instructor_id' => 10, 'created_at' => $now, 'updated_at' => $now],
        ['id' => 2, 'title' => 'Beta', 'instructor_id' => 10, 'created_at' => $now, 'updated_at' => $now],
        ['id' => 3, 'title' => 'Gamma', 'instructor_id' => 11, 'created_at' => $now, 'updated_at' => $now],
        ['id' => 4, 'title' => 'Delta', 'instructor_id' => 10, 'created_at' => $now, 'updated_at' => $now],
        ['id' => 5, 'title' => 'Echo', 'instructor_id' => 10, 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('student_labels')->insert([
        ['id' => 1, 'name' => 'Scholars', 'color' => '#ca8a04', 'created_at' => $now, 'updated_at' => $now],
        ['id' => 2, 'name' => 'PEF', 'color' => '#2563eb', 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('student_label_user')->insert([
        ['user_id' => 1, 'student_label_id' => 2, 'created_at' => $now, 'updated_at' => $now],
        ['user_id' => 3, 'student_label_id' => 1, 'created_at' => $now, 'updated_at' => $now],
    ]);

    $enroll = function (int $userId, int $courseId, ?string $status = 'active', ?string $balancePaidAt = null) use ($now) {
        DB::table('course_enrollments')->insert([
            'user_id' => $userId,
            'course_id' => $courseId,
            'access_status' => $status,
            'balance_paid_at' => $balancePaidAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    };

    // Ada: one active course, and a duplicate row that must not count twice. Label named PEF, not the PEF flag.
    $enroll(1, 1);
    $enroll(1, 1);
    // Bea: PEF flag, two reserved seats only.
    $enroll(2, 1, 'reserved');
    $enroll(2, 2, 'reserved');
    // Cam: Scholars label, one reserved seat and one full course.
    $enroll(3, 2, 'reserved');
    $enroll(3, 3, 'active');
    // Dee: four courses, so not in the one, two, or three course rows.
    $enroll(4, 1);
    $enroll(4, 2);
    $enroll(4, 4);
    $enroll(4, 5);
}

test('the overview counts distinct students and keeps a student in two courses inside the total once', function () {
    overviewFixture();

    $overview = (new CourseEnrollmentService())->enrollmentOverview([]);

    expect($overview['filtered'])->toBeFalse()
        ->and($overview['total_students'])->toBe(4)
        ->and($overview['unfiltered_total_students'])->toBe(4)
        ->and(collect($overview['rows'])->where('kind', 'course')->values()->all())->toBe([
            ['label' => 'Alpha', 'students' => 3, 'kind' => 'course'],
            ['label' => 'Beta', 'students' => 3, 'kind' => 'course'],
            ['label' => 'Delta', 'students' => 1, 'kind' => 'course'],
            ['label' => 'Echo', 'students' => 1, 'kind' => 'course'],
            ['label' => 'Gamma', 'students' => 1, 'kind' => 'course'],
        ])
        ->and(collect($overview['rows'])->where('kind', 'group')->values()->all())->toBe([
            ['label' => 'Pre-registered Only', 'students' => 1, 'kind' => 'group'],
            ['label' => 'Enrolled in one course only', 'students' => 1, 'kind' => 'group'],
            ['label' => 'Two courses enrolled', 'students' => 2, 'kind' => 'group'],
            ['label' => 'Three courses enrolled', 'students' => 0, 'kind' => 'group'],
        ]);
});

test('a label filter matches any selected label and the pef chip uses the pef flag', function () {
    overviewFixture();

    $service = new CourseEnrollmentService();
    $byLabelNamedPef = $service->enrollmentOverview([], [2], false);
    $byPefFlag = $service->enrollmentOverview([], [], true);
    $either = $service->enrollmentOverview([], [1], true);

    expect($byLabelNamedPef['total_students'])->toBe(1)
        ->and($byLabelNamedPef['filtered'])->toBeTrue()
        ->and($byLabelNamedPef['unfiltered_total_students'])->toBe(4)
        ->and(collect($byLabelNamedPef['rows'])->firstWhere('label', 'Alpha')['students'])->toBe(1)
        ->and($byPefFlag['total_students'])->toBe(1)
        ->and(collect($byPefFlag['rows'])->firstWhere('label', 'Alpha')['students'])->toBe(1)
        ->and(collect($byPefFlag['rows'])->firstWhere('label', 'Beta')['students'])->toBe(1)
        ->and($either['total_students'])->toBe(2);
});

test('a paid reserved seat is not pre-registered only', function () {
    $now = now();

    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Ada', 'pef_tagged' => 0, 'created_at' => $now, 'updated_at' => $now],
    ]);
    DB::table('courses')->insert([
        ['id' => 1, 'title' => 'Alpha', 'instructor_id' => 10, 'created_at' => $now, 'updated_at' => $now],
    ]);
    DB::table('course_enrollments')->insert([
        'user_id' => 1,
        'course_id' => 1,
        'access_status' => 'reserved',
        'balance_paid_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $overview = (new CourseEnrollmentService())->enrollmentOverview([]);

    expect(collect($overview['rows'])->firstWhere('label', 'Pre-registered Only')['students'])->toBe(0)
        ->and(collect($overview['rows'])->firstWhere('label', 'Enrolled in one course only')['students'])->toBe(1);
});

test('the overview qualifies tables with the database prefix', function () {
    Schema::dropIfExists('student_label_user');
    Schema::dropIfExists('student_labels');
    Schema::dropIfExists('course_enrollments');
    Schema::dropIfExists('courses');
    Schema::dropIfExists('users');
    DB::connection()->setTablePrefix('ssa_academy_');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->boolean('pef_tagged')->default(false);
        $table->timestamps();
    });

    Schema::create('student_labels', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('color');
        $table->timestamps();
    });

    Schema::create('student_label_user', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('student_label_id');
        $table->timestamps();
    });

    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->unsignedBigInteger('instructor_id')->nullable();
        $table->timestamps();
    });

    Schema::create('course_enrollments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->string('access_status')->nullable();
        $table->timestamp('balance_paid_at')->nullable();
        $table->timestamp('forfeited_at')->nullable();
        $table->timestamps();
    });

    $now = now();

    DB::table('users')->insert([
        ['id' => 1, 'pef_tagged' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['id' => 2, 'pef_tagged' => 0, 'created_at' => $now, 'updated_at' => $now],
    ]);
    DB::table('courses')->insert([
        ['id' => 1, 'title' => 'Alpha', 'instructor_id' => 10, 'created_at' => $now, 'updated_at' => $now],
    ]);
    DB::table('student_labels')->insert([
        ['id' => 1, 'name' => 'Scholars', 'color' => '#ca8a04', 'created_at' => $now, 'updated_at' => $now],
    ]);
    DB::table('student_label_user')->insert([
        ['user_id' => 2, 'student_label_id' => 1, 'created_at' => $now, 'updated_at' => $now],
    ]);
    DB::table('course_enrollments')->insert([
        ['user_id' => 1, 'course_id' => 1, 'access_status' => 'active', 'created_at' => $now, 'updated_at' => $now],
        ['user_id' => 2, 'course_id' => 1, 'access_status' => 'active', 'created_at' => $now, 'updated_at' => $now],
    ]);

    $overview = (new CourseEnrollmentService())->enrollmentOverview([], [1], false);

    expect($overview['total_students'])->toBe(1)
        ->and($overview['unfiltered_total_students'])->toBe(2)
        ->and(collect($overview['rows'])->firstWhere('label', 'Alpha')['students'])->toBe(1);
});

test('a trainer overview only counts that trainer courses', function () {
    overviewFixture();

    $overview = (new CourseEnrollmentService())->enrollmentOverview(['instructor_id' => 11]);

    expect(collect($overview['rows'])->where('kind', 'course')->values()->all())->toBe([
        ['label' => 'Gamma', 'students' => 1, 'kind' => 'course'],
    ])->and($overview['total_students'])->toBe(1)
        ->and(collect($overview['rows'])->firstWhere('label', 'Enrolled in one course only')['students'])->toBe(1)
        ->and(collect($overview['rows'])->firstWhere('label', 'Two courses enrolled')['students'])->toBe(0);
});
