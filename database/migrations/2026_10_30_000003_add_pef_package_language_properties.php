<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Language\Models\Language;
use Modules\Language\Models\LanguageProperty;

return new class extends Migration
{
    private array $settingsKeys = [
        'pef_package' => 'PEF package',
        'pef_package_help' => 'Tagging an external student as PEF enrolls them in every course selected here. Access lasts for the period below, counted from the day they are tagged. After that, finished lessons stay open and unfinished items lock.',
        'pef_enabled' => 'PEF package',
        'pef_courses' => 'Package courses',
        'pef_access_duration' => 'Access period',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('language_properties')) {
            return;
        }

        $property = LanguageProperty::query()
            ->where('group', 'settings')
            ->orderBy('id')
            ->first();

        if (! $property) {
            return;
        }

        $properties = is_array($property->properties) ? $property->properties : [];
        $changed = false;

        foreach ($this->settingsKeys as $key => $value) {
            if (! array_key_exists($key, $properties) || $properties[$key] === '' || $properties[$key] === null) {
                $properties[$key] = $value;
                $changed = true;
            }
        }

        if ($changed) {
            $property->update(['properties' => $properties]);
        }

        Language::query()->pluck('code')->each(function (string $code): void {
            Cache::forget('language_properties:'.$code);
        });

        Cache::forget('language_properties');
    }

    public function down(): void
    {
        // Keep translation keys on rollback.
    }
};
