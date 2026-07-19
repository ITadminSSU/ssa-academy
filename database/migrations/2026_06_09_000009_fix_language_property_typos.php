<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Language\Models\LanguageProperty;

return new class extends Migration
{
    private array $replacements = [
        'True of False' => 'True or False',
        'Summery' => 'Summary',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('language_properties')) {
            return;
        }

        LanguageProperty::query()->each(function (LanguageProperty $property): void {
            $properties = $property->properties;

            if (!is_array($properties)) {
                return;
            }

            $changed = false;

            foreach ($properties as $key => $value) {
                if (!is_string($value) || !array_key_exists($value, $this->replacements)) {
                    continue;
                }

                $properties[$key] = $this->replacements[$value];
                $changed = true;
            }

            if ($changed) {
                $property->update(['properties' => $properties]);
            }
        });

        Cache::forget('language_properties');
    }

    public function down(): void
    {
        if (!Schema::hasTable('language_properties')) {
            return;
        }

        $reverse = array_flip($this->replacements);

        LanguageProperty::query()->each(function (LanguageProperty $property) use ($reverse): void {
            $properties = $property->properties;

            if (!is_array($properties)) {
                return;
            }

            $changed = false;

            foreach ($properties as $key => $value) {
                if (!is_string($value) || !array_key_exists($value, $reverse)) {
                    continue;
                }

                $properties[$key] = $reverse[$value];
                $changed = true;
            }

            if ($changed) {
                $property->update(['properties' => $properties]);
            }
        });

        Cache::forget('language_properties');
    }
};
