<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Language\Models\Language;
use Modules\Language\Models\LanguageProperty;

return new class extends Migration
{
    private array $settingsKeys = [
        'site_alert' => 'Site-wide alert bar',
        'site_alert_help' => 'When On, every visitor sees this message at the top of the site. Turn it Off when maintenance is finished.',
        'site_alert_enabled' => 'Show alert',
        'site_alert_message' => 'Alert message',
        'site_alert_message_placeholder' => 'Example: The academy will be down for about 15 minutes while we upgrade the server.',
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
