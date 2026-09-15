<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Language\Models\Language;
use Modules\Language\Models\LanguageProperty;

return new class extends Migration
{
    private array $settingsKeys = [
        'companion_course_auto_enroll' => 'Companion course auto-enroll',
        'companion_course_auto_enroll_help' => 'When a learner gets full access to any other course, they are also enrolled in this free course. Deposit-only seats wait until the balance is paid. Leave this Off until you pick a course.',
        'companion_course' => 'Companion course',
        'companion_course_placeholder' => 'Select a free course',
        'companion_auto_enroll_enabled' => 'Auto-enroll',
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
