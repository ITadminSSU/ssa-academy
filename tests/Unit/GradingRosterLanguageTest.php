<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Language\Models\Language;
use Modules\Language\Models\LanguageProperty;

beforeEach(function () {
    if (! Schema::hasTable('languages')) {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->string('flag')->nullable();
            $table->string('nativeName')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('language_properties')) {
        Schema::create('language_properties', function (Blueprint $table) {
            $table->id();
            $table->string('group');
            $table->string('name');
            $table->string('slug');
            $table->json('properties');
            $table->foreignId('language_id')->constrained('languages')->cascadeOnDelete();
            $table->timestamps();
        });
    }
});

it('stores the grading roster labels for languages that were saved before those keys existed', function () {
    $english = Language::query()->create([
        'name' => 'English',
        'code' => 'en',
        'is_active' => true,
        'is_default' => true,
    ]);

    LanguageProperty::query()->create([
        'language_id' => $english->id,
        'group' => 'dashboard',
        'name' => 'Course Progress',
        'slug' => 'course_progress',
        'properties' => [
            'student' => 'Student',
            'quizzes' => 'Custom Quizzes',
            'course_score' => '',
        ],
    ]);

    $spanish = Language::query()->create([
        'name' => 'Spanish',
        'code' => 'es',
        'is_active' => true,
        'is_default' => false,
    ]);

    $migration = require base_path('database/migrations/2026_11_11_000002_add_grading_roster_language_properties.php');
    $migration->up();

    $englishProperties = LanguageProperty::query()
        ->where('language_id', $english->id)
        ->where('slug', 'course_progress')
        ->first()
        ->properties;

    expect($englishProperties['student'])->toBe('Student')
        ->and($englishProperties['quizzes'])->toBe('Custom Quizzes')
        ->and($englishProperties['course_score'])->toBe('Course Score')
        ->and($englishProperties['best_quiz_score'])->toBe('Best Quiz')
        ->and($englishProperties['completion'])->toBe('Completion')
        ->and($englishProperties['standalone_exams'])->toBe('Standalone Exams')
        ->and($englishProperties['certificate'])->toBe('Certificate')
        ->and($englishProperties['details'])->toBe('Details')
        ->and($englishProperties['in_progress'])->toBe('In Progress')
        ->and($englishProperties['certificate_ready'])->toBe('Ready');

    $spanishProperties = LanguageProperty::query()
        ->where('language_id', $spanish->id)
        ->where('group', 'dashboard')
        ->first()
        ->properties;

    expect($spanishProperties['course_score'])->toBe('Course Score')
        ->and($spanishProperties['passed_label'])->toBe('passed');

    $englishProperties['course_score'] = 'Overall';
    LanguageProperty::query()
        ->where('language_id', $english->id)
        ->where('slug', 'course_progress')
        ->update(['properties' => $englishProperties]);

    $migration->up();

    $kept = LanguageProperty::query()
        ->where('language_id', $english->id)
        ->where('slug', 'course_progress')
        ->first()
        ->properties;

    expect($kept['course_score'])->toBe('Overall');
});

it('ships the same grading roster labels in the language catalogs', function () {
    $english = include base_path('lang/en/dashboard.php');
    $default = include base_path('storage/app/lang/default/dashboard.php');
    $groups = include base_path('storage/app/lang/groups/dashboard.php');
    $flat = [];

    foreach ($groups as $block) {
        foreach ($block['properties'] as $key => $value) {
            $flat[$key] = $value;
        }
    }

    $labels = [
        'course_score' => 'Course Score',
        'best_quiz_score' => 'Best Quiz',
        'completion' => 'Completion',
        'standalone_exams' => 'Standalone Exams',
        'certificate' => 'Certificate',
        'certificate_ready' => 'Ready',
        'in_progress' => 'In Progress',
        'details' => 'Details',
        'submitted' => 'submitted',
        'passed_label' => 'passed',
        'attempts' => 'Attempts',
    ];

    foreach ($labels as $key => $value) {
        expect($english[$key])->toBe($value)
            ->and($default[$key])->toBe($value)
            ->and($flat[$key])->toBe($value);
    }
});
