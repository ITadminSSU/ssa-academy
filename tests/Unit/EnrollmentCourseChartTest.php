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

test('enrollment chart counts each student once and sorts by that count', function () {
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
        ['course' => 'Alpha', 'students' => 2],
        ['course' => 'Beta', 'students' => 1],
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
        ['course' => 'Alpha', 'students' => 2],
    ])->and($service->enrollmentCountsByCourse(['user_id' => 1]))->toBe([
        ['course' => 'Alpha', 'students' => 1],
        ['course' => 'Beta', 'students' => 1],
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
        ['course' => 'Alpha', 'students' => 2],
    ]);
});
