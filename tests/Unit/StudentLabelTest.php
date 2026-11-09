<?php

use App\Models\Setting;
use App\Models\StudentLabel;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\StudentLabelService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('student_label_user');
    Schema::dropIfExists('student_labels');
    Schema::dropIfExists('settings');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('role')->nullable();
        $table->string('user_type')->nullable();
        $table->boolean('pef_tagged')->default(false);
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
        $table->foreignId('user_id');
        $table->foreignId('student_label_id');
        $table->timestamps();
    });

    Schema::create('settings', function (Blueprint $table) {
        $table->id();
        $table->string('type')->nullable();
        $table->string('sub_type')->nullable();
        $table->json('fields')->nullable();
        $table->timestamps();
    });
});

test('assigning a label does not change pef or enroll a course', function () {
    $student = User::query()->create([
        'name' => 'Ada Student',
        'email' => 'ada@example.com',
        'role' => 'student',
        'user_type' => 'external',
        'pef_tagged' => false,
    ]);
    $label = StudentLabel::query()->create([
        'name' => 'EVT',
        'color' => '#2563eb',
    ]);

    app(StudentLabelService::class)->syncForUser($student, [$label->id]);

    expect($student->fresh()->pef_tagged)->toBeFalse()
        ->and($student->studentLabels()->pluck('name')->all())->toBe(['EVT']);
});

test('labels are removed from someone who is not a student', function () {
    $admin = User::query()->create([
        'name' => 'Ops',
        'email' => 'ops@example.com',
        'role' => 'admin',
        'pef_tagged' => false,
    ]);
    $label = StudentLabel::query()->create([
        'name' => 'Scholars',
        'color' => '#ca8a04',
    ]);
    $admin->studentLabels()->attach($label->id);

    app(StudentLabelService::class)->syncForUser($admin, [$label->id]);

    expect($admin->studentLabels()->count())->toBe(0);
});

test('enrollment setting pages update only their own fields', function () {
    Setting::query()->create([
        'type' => 'system',
        'fields' => [
            'name' => 'Academy',
            'pef_enabled' => true,
            'pef_course_ids' => [],
            'pef_access_duration' => '2 years',
            'companion_auto_enroll_enabled' => true,
            'companion_course_id' => 9,
        ],
    ]);

    app(SettingsService::class)->updateEnrollmentSettings([
        'pef_enabled' => false,
        'pef_access_duration' => '1 year',
        'pef_course_ids' => [],
    ]);

    $fields = Setting::query()->where('type', 'system')->first()->fields;

    expect($fields['name'])->toBe('Academy')
        ->and($fields['pef_enabled'])->toBeFalse()
        ->and($fields['pef_access_duration'])->toBe('1 year')
        ->and($fields['companion_auto_enroll_enabled'])->toBeTrue()
        ->and($fields['companion_course_id'])->toBe(9);
});

test('a system settings save does not overwrite pef or companion settings', function () {
    $setting = Setting::query()->create([
        'type' => 'system',
        'fields' => [
            'name' => 'Academy',
            'email' => 'old@example.com',
            'pef_enabled' => true,
            'pef_course_ids' => [4],
            'pef_access_duration' => '2 years',
            'companion_auto_enroll_enabled' => true,
            'companion_course_id' => 9,
        ],
    ]);

    app(SettingsService::class)->systemUpdate([
        'name' => 'Academy',
        'email' => 'new@example.com',
        'pef_enabled' => false,
        'companion_auto_enroll_enabled' => false,
        'companion_course_id' => null,
    ], (string) $setting->id);

    $fields = $setting->fresh()->fields;

    expect($fields['email'])->toBe('new@example.com')
        ->and($fields['pef_enabled'])->toBeTrue()
        ->and($fields['pef_course_ids'])->toBe([4])
        ->and($fields['companion_auto_enroll_enabled'])->toBeTrue()
        ->and($fields['companion_course_id'])->toBe(9);
});
