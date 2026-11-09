<?php

use App\Models\Course\Course;
use App\Services\Course\CourseEnrollmentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('payment_histories');
    Schema::dropIfExists('student_label_user');
    Schema::dropIfExists('student_labels');
    Schema::dropIfExists('course_enrollments');
    Schema::dropIfExists('courses');

    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->timestamps();
    });

    Schema::create('course_enrollments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->timestamp('entry_date')->nullable();
        $table->timestamps();
    });

    Schema::create('student_labels', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('student_label_user', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('student_label_id');
    });

    Schema::create('payment_histories', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('purchase_id');
        $table->string('purchase_type');
        $table->string('coupon')->nullable();
        $table->text('meta')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('payment_histories');
    Schema::dropIfExists('student_label_user');
    Schema::dropIfExists('student_labels');
    Schema::dropIfExists('course_enrollments');
    Schema::dropIfExists('courses');
});

test('course enrollment list keeps newest first unless a sort is requested', function () {
    $older = now()->subDay();
    $newer = now();

    DB::table('courses')->insert([
        ['id' => 1, 'title' => 'Zulu', 'created_at' => $older, 'updated_at' => $older],
        ['id' => 2, 'title' => 'Alpha', 'created_at' => $older, 'updated_at' => $older],
    ]);

    DB::table('course_enrollments')->insert([
        ['id' => 1, 'user_id' => 1, 'course_id' => 1, 'entry_date' => $newer, 'created_at' => $older, 'updated_at' => $older],
        ['id' => 2, 'user_id' => 2, 'course_id' => 2, 'entry_date' => $older, 'created_at' => $newer, 'updated_at' => $newer],
    ]);

    $service = new CourseEnrollmentService();

    expect($service->listEnrollmentIds([]))->toBe([2, 1])
        ->and($service->listEnrollmentIds(['sort' => 'course', 'direction' => 'asc']))->toBe([2, 1])
        ->and($service->listEnrollmentIds(['sort' => 'enrolled_date', 'direction' => 'asc']))->toBe([2, 1])
        ->and($service->listEnrollmentIds(['sort' => 'enrolled_date', 'direction' => 'desc']))->toBe([1, 2]);
});

test('course enrollment list can sort by coupon and student tag without changing other callers', function () {
    $now = now();

    DB::table('courses')->insert([
        ['id' => 1, 'title' => 'Alpha', 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('course_enrollments')->insert([
        ['id' => 1, 'user_id' => 1, 'course_id' => 1, 'entry_date' => $now, 'created_at' => $now, 'updated_at' => $now],
        ['id' => 2, 'user_id' => 2, 'course_id' => 1, 'entry_date' => $now, 'created_at' => $now, 'updated_at' => $now],
        ['id' => 3, 'user_id' => 3, 'course_id' => 1, 'entry_date' => $now, 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('student_labels')->insert([
        ['id' => 5, 'name' => 'Zulu', 'created_at' => $now, 'updated_at' => $now],
        ['id' => 8, 'name' => 'Alpha', 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('student_label_user')->insert([
        ['user_id' => 1, 'student_label_id' => 5],
        ['user_id' => 2, 'student_label_id' => 8],
    ]);

    DB::table('payment_histories')->insert([
        [
            'user_id' => 1,
            'purchase_id' => 1,
            'purchase_type' => Course::class,
            'coupon' => 'SAVE10',
            'meta' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'user_id' => 3,
            'purchase_id' => 1,
            'purchase_type' => Course::class,
            'coupon' => null,
            'meta' => json_encode(['coupon_code' => 'BETA']),
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    $service = new CourseEnrollmentService();

    expect($service->matchingEnrollmentIds(['label_ids' => [8]]))->toBe([2])
        ->and($service->matchingEnrollmentIds(['label_ids' => [5, 8]]))->toBe([1, 2])
        ->and($service->listEnrollmentIds(['sort' => 'tags', 'direction' => 'asc']))->toBe([2, 1, 3])
        ->and($service->listEnrollmentIds(['sort' => 'coupon', 'direction' => 'asc']))->toBe([3, 1, 2]);
});
