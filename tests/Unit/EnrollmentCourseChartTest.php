<?php

use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Services\Course\CourseEnrollmentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('course_enrollments');
    Schema::dropIfExists('courses');

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
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('course_enrollments');
    Schema::dropIfExists('courses');
    DB::connection()->setTablePrefix('');
});

test('enrollment chart counts every enrollment row and sorts by that count', function () {
    $now = now();

    DB::table('courses')->insert([
        ['id' => 1, 'title' => 'Alpha', 'instructor_id' => 10, 'created_at' => $now, 'updated_at' => $now],
        ['id' => 2, 'title' => 'Beta', 'instructor_id' => 11, 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('course_enrollments')->insert([
        ['user_id' => 1, 'course_id' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['user_id' => 1, 'course_id' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['user_id' => 2, 'course_id' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['user_id' => 3, 'course_id' => 2, 'created_at' => $now, 'updated_at' => $now],
    ]);

    $rows = (new CourseEnrollmentService())->enrollmentCountsByCourse([]);

    expect($rows)->toBe([
        ['course' => 'Alpha', 'enrollments' => 3],
        ['course' => 'Beta', 'enrollments' => 1],
    ]);
});

test('enrollment chart keeps the instructor and learner scopes', function () {
    $now = now();

    DB::table('courses')->insert([
        ['id' => 1, 'title' => 'Alpha', 'instructor_id' => 10, 'created_at' => $now, 'updated_at' => $now],
        ['id' => 2, 'title' => 'Beta', 'instructor_id' => 11, 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('course_enrollments')->insert([
        ['user_id' => 1, 'course_id' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['user_id' => 2, 'course_id' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['user_id' => 1, 'course_id' => 2, 'created_at' => $now, 'updated_at' => $now],
    ]);

    $service = new CourseEnrollmentService();

    expect($service->enrollmentCountsByCourse(['instructor_id' => 10]))->toBe([
        ['course' => 'Alpha', 'enrollments' => 2],
    ])->and($service->enrollmentCountsByCourse(['user_id' => 1]))->toBe([
        ['course' => 'Alpha', 'enrollments' => 1],
        ['course' => 'Beta', 'enrollments' => 1],
    ]);

    expect((new Course())->getTable())->toBe('courses')
        ->and((new CourseEnrollment())->getTable())->toBe('course_enrollments');
});

test('enrollment chart qualifies tables with the database prefix', function () {
    Schema::dropIfExists('course_enrollments');
    Schema::dropIfExists('courses');
    DB::connection()->setTablePrefix('ssa_academy_');

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
        $table->timestamps();
    });

    $now = now();

    DB::table('courses')->insert([
        ['id' => 1, 'title' => 'Alpha', 'instructor_id' => 10, 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('course_enrollments')->insert([
        ['user_id' => 1, 'course_id' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['user_id' => 2, 'course_id' => 1, 'created_at' => $now, 'updated_at' => $now],
    ]);

    expect((new CourseEnrollmentService())->enrollmentCountsByCourse([]))->toBe([
        ['course' => 'Alpha', 'enrollments' => 2],
    ]);
});

test('enrollment chart keeps only students who have any selected label', function () {
    Schema::dropIfExists('student_label_user');
    Schema::create('student_label_user', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('student_label_id');
    });

    $now = now();

    DB::table('courses')->insert([
        ['id' => 1, 'title' => 'Alpha', 'instructor_id' => null, 'created_at' => $now, 'updated_at' => $now],
        ['id' => 2, 'title' => 'Beta', 'instructor_id' => null, 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('course_enrollments')->insert([
        ['id' => 1, 'user_id' => 1, 'course_id' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['id' => 2, 'user_id' => 1, 'course_id' => 2, 'created_at' => $now, 'updated_at' => $now],
        ['id' => 3, 'user_id' => 2, 'course_id' => 1, 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('student_label_user')->insert([
        ['user_id' => 1, 'student_label_id' => 5],
        ['user_id' => 2, 'student_label_id' => 8],
    ]);

    $service = new CourseEnrollmentService();

    expect($service->enrollmentCountsByCourse(['label_ids' => [5, 8]]))->toBe([
        ['course' => 'Alpha', 'enrollments' => 2],
        ['course' => 'Beta', 'enrollments' => 1],
    ])->and($service->enrollmentCountsByCourse(['label_ids' => [5]]))->toBe([
        ['course' => 'Alpha', 'enrollments' => 1],
        ['course' => 'Beta', 'enrollments' => 1],
    ])->and($service->enrollmentCountsByCourse(['label_ids' => [8]]))->toBe([
        ['course' => 'Alpha', 'enrollments' => 1],
    ]);

    Schema::dropIfExists('student_label_user');
});
