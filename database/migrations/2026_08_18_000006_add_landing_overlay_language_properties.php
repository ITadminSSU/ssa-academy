<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Language\Models\Language;
use Modules\Language\Models\LanguageProperty;

return new class extends Migration
{
    private array $settingsKeys = [
        'landing_overlay' => 'Landing overlay',
        'landing_overlay_description' => 'Show a dismissible full-screen message on the public home page. Visitors who close it will not see it again until you change the content.',
        'preview_overlay' => 'Preview overlay',
        'overlay_headline' => 'Headline',
        'overlay_pains_title' => 'Pain points title',
        'overlay_pains' => 'Pain points',
        'add_pain_point' => 'Add pain point',
        'overlay_solution_title' => 'Solution title',
        'overlay_solution' => 'Solution message',
        'overlay_cta_label' => 'Button label',
        'overlay_cta_url' => 'Button link',
        'overlay_cta_url_hint' => 'Use a site path like /register, or a full http(s) URL.',
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
