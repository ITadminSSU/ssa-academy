<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Language\Models\LanguageProperty;

return new class extends Migration
{
    private array $keys = [
        'course_audience' => 'Course audience',
        'course_audience_internal' => 'Internal (employees only)',
        'course_audience_public' => 'Public (catalog visible to everyone)',
        'course_audience_both' => 'Both (internal employees and public learners)',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('language_properties')) {
            return;
        }

        LanguageProperty::query()
            ->where('slug', 'courses_lessons')
            ->each(function (LanguageProperty $property): void {
                $properties = $property->properties;

                if (!is_array($properties)) {
                    return;
                }

                $changed = false;

                foreach ($this->keys as $key => $value) {
                    if (!array_key_exists($key, $properties) || $properties[$key] === '' || $properties[$key] === null) {
                        $properties[$key] = $value;
                        $changed = true;
                    }
                }

                if ($changed) {
                    $property->update(['properties' => $properties]);
                }
            });

        Cache::forget('language_properties');
    }

    public function down(): void
    {
        // Translation keys are kept on rollback.
    }
};
