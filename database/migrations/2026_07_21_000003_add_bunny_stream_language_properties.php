<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Language\Models\Language;
use Modules\Language\Models\LanguageProperty;

return new class extends Migration
{
    private array $inputKeys = [
        'bunny_stream_enabled' => 'Enable Bunny Stream for lesson videos',
        'bunny_stream_library_id' => 'Stream Library ID',
        'bunny_stream_api_key' => 'Stream API Key',
        'bunny_stream_cdn_hostname' => 'CDN Hostname',
        'bunny_stream_token_auth_key' => 'Token Authentication Key',
        'bunny_stream_library_id_placeholder' => '710026',
        'bunny_stream_api_key_placeholder' => 'Enter your Bunny Stream API key',
        'bunny_stream_cdn_hostname_placeholder' => 'vz-xxxxx.b-cdn.net',
        'bunny_stream_token_auth_key_placeholder' => 'Enter your Bunny token authentication key',
        'aws_endpoint' => 'S3 Endpoint URL',
        'aws_use_path_style_endpoint' => 'Use path-style endpoint',
        'aws_endpoint_placeholder' => 'https://<account_id>.r2.cloudflarestorage.com',
    ];

    private array $settingsKeys = [
        'bunny_stream_settings' => 'Bunny Stream Settings',
        'bunny_stream_settings_description' => 'Configure Bunny Stream for lesson video hosting and playback',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('language_properties')) {
            return;
        }

        $this->mergeKeysIntoGroup('input', $this->inputKeys);
        $this->mergeKeysIntoGroup('settings', $this->settingsKeys);

        Language::query()->pluck('code')->each(function (string $code): void {
            Cache::forget('language_properties:' . $code);
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
    private function mergeKeysIntoGroup(string $group, array $keys): void
    {
        $property = LanguageProperty::query()
            ->where('group', $group)
            ->orderBy('id')
            ->first();

        if (!$property) {
            return;
        }

        $properties = $property->properties;

        if (!is_array($properties)) {
            $properties = [];
        }

        $changed = false;

        foreach ($keys as $key => $value) {
            if (!array_key_exists($key, $properties) || $properties[$key] === '' || $properties[$key] === null) {
                $properties[$key] = $value;
                $changed = true;
            }
        }

        if ($changed) {
            $property->update(['properties' => $properties]);
        }
    }
};
