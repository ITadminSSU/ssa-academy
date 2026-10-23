<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! class_exists(Setting::class)) {
            return;
        }

        Setting::query()->firstOrCreate(
            ['type' => 'go_high_level'],
            [
                'sub_type' => null,
                'title' => 'Go High Level',
                'fields' => [
                    'enabled' => false,
                    'webhook_url' => '',
                ],
            ],
        );
    }

    public function down(): void
    {
        Setting::query()->where('type', 'go_high_level')->delete();
    }
};
