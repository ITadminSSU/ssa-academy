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

        LanguageProperty::query()->each(function (LanguageProperty $property): void {
            $properties = $property->properties;

            if (! is_array($properties)) {
                return;
            }

            $updated = $this->replaceTypos($properties);

            if ($updated !== $properties) {
                $property->update(['properties' => $updated]);
            }
        });

        $this->forgetLanguageCache();
    }

    public function down(): void
    {
        // Keep the corrected copy.
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function replaceTypos(array $properties): array
    {
        foreach ($properties as $key => $value) {
            if (is_array($value)) {
                $properties[$key] = $this->replaceTypos($value);
                continue;
            }

            if (is_string($value) && $value === 'True of False') {
                $properties[$key] = 'True or False';
            }
        }

        return $properties;
    }

    private function forgetLanguageCache(): void
    {
        if (class_exists(Language::class) && Schema::hasTable('languages')) {
            Language::query()->pluck('code')->each(function (string $code): void {
                Cache::forget('language_properties:'.$code);
            });
        }

        Cache::forget('language_properties');
    }
};
