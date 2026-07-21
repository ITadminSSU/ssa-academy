<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!class_exists(Setting::class)) {
            return;
        }

        Setting::query()->firstOrCreate(
            ['type' => 'bunny_stream'],
            [
                'sub_type' => null,
                'title' => 'Bunny Stream Settings',
                'fields' => [
                    'enabled' => false,
                    'library_id' => '',
                    'api_key' => '',
                    'cdn_hostname' => '',
                    'token_auth_key' => '',
                ],
            ]
        );
    }

    public function down(): void
    {
        Setting::query()->where('type', 'bunny_stream')->delete();
    }
};
