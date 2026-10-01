<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Language\Models\Language;
use Modules\Language\Models\LanguageProperty;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('language_properties')) {
            return;
        }

        $this->mergeKeys('dashboard', 'course_progress', [
            'enrolled_students' => 'Enrolled Students',
        ]);

        $this->mergeKeys('button', 'pagination_buttons', [
            'view_progress' => 'View Progress',
        ]);

        Language::query()->pluck('code')->each(function (string $code): void {
            Cache::forget('language_properties:'.$code);
        });

        Cache::forget('language_properties');
    }

    public function down(): void
    {
        // Keep translation keys on rollback.
    }

    /**
     * @param  array<string, string>  $keys
     */
    private function mergeKeys(string $group, string $slug, array $keys): void
    {
        $languageIds = Language::query()->pluck('id');

        foreach ($languageIds as $languageId) {
            $property = LanguageProperty::query()
                ->where('language_id', $languageId)
                ->where('group', $group)
                ->where('slug', $slug)
                ->first();

            if (! $property) {
                $property = LanguageProperty::query()
                    ->where('language_id', $languageId)
                    ->where('group', $group)
                    ->orderBy('id')
                    ->first();
            }

            if (! $property) {
                continue;
            }

            $properties = is_array($property->properties) ? $property->properties : [];
            $changed = false;

            foreach ($keys as $key => $value) {
                if (! array_key_exists($key, $properties) || $properties[$key] === '' || $properties[$key] === null) {
                    $properties[$key] = $value;
                    $changed = true;
                }
            }

            if ($changed) {
                $property->update(['properties' => $properties]);
            }
        }
    }
};
