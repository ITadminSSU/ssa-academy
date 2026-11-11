<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Language\Models\Language;
use Modules\Language\Models\LanguageProperty;

return new class extends Migration
{
    /**
     * Labels for the student grading roster. These live in lang/en already, but a saved
     * language group marks that group as loaded and hides any key that was never stored.
     *
     * @var array<string, string>
     */
    private array $keys = [
        'student_progress' => 'Student Progress',
        'student_progress_course_description' => 'Enrollment list with completion, quiz scores, assignment status, and standalone exam results.',
        'grading_roster_description' => 'Course-wide grading roster sorted by overall performance score (highest first).',
        'enrollment_list' => 'Enrollment List',
        'course_score' => 'Course Score',
        'best_quiz_score' => 'Best Quiz',
        'completion' => 'Completion',
        'standalone_exams' => 'Standalone Exams',
        'no_standalone_exams' => 'No standalone exam attempts for this instructor.',
        'certificate' => 'Certificate',
        'certificate_ready' => 'Ready',
        'in_progress' => 'In Progress',
        'details' => 'Details',
        'submitted' => 'submitted',
        'passed_label' => 'passed',
        'attempts' => 'Attempts',
        'sort_students_by' => 'Sort by',
        'sort_by_name' => 'Student name (A–Z)',
        'sort_by_completion' => 'Completion (highest first)',
        'sort_by_overall_score' => 'Course score (highest first)',
        'sort_by_best_quiz' => 'Best quiz score (highest first)',
        'no_score_yet' => 'No scores yet',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('language_properties') || ! Schema::hasTable('languages')) {
            return;
        }

        Language::query()->pluck('id')->each(function ($languageId): void {
            $this->mergeKeys((int) $languageId);
        });

        Language::query()->pluck('code')->each(function (string $code): void {
            Cache::forget('language_properties:'.$code);
        });

        Cache::forget('language_properties');
    }

    public function down(): void
    {
        // Keep translation keys on rollback.
    }

    private function mergeKeys(int $languageId): void
    {
        $property = LanguageProperty::query()
            ->where('language_id', $languageId)
            ->where('group', 'dashboard')
            ->where('slug', 'course_progress')
            ->first();

        if (! $property) {
            $property = LanguageProperty::query()
                ->where('language_id', $languageId)
                ->where('group', 'dashboard')
                ->orderBy('id')
                ->first();
        }

        if (! $property) {
            LanguageProperty::query()->create([
                'language_id' => $languageId,
                'group' => 'dashboard',
                'name' => 'Course Progress',
                'slug' => 'course_progress',
                'properties' => $this->keys,
            ]);

            return;
        }

        $properties = is_array($property->properties) ? $property->properties : [];
        $changed = false;

        foreach ($this->keys as $key => $value) {
            if (! array_key_exists($key, $properties) || $properties[$key] === '' || $properties[$key] === null) {
                $properties[$key] = $value;
                $changed = true;
            }
        }

        if ($changed) {
            $property->update(['properties' => $properties]);
        }
    }
};
