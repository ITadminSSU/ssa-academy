<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')
            ->where('type', 'system')
            ->orderBy('id')
            ->chunkById(20, function ($settings) {
                foreach ($settings as $setting) {
                    $fields = json_decode($setting->fields, true);

                    if (! is_array($fields)) {
                        continue;
                    }

                    if (! array_key_exists('site_alert_enabled', $fields)) {
                        $fields['site_alert_enabled'] = false;
                    }

                    if (! array_key_exists('site_alert_message', $fields)) {
                        $fields['site_alert_message'] = '';
                    }

                    DB::table('settings')->where('id', $setting->id)->update([
                        'fields' => json_encode($fields),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')
            ->where('type', 'system')
            ->orderBy('id')
            ->chunkById(20, function ($settings) {
                foreach ($settings as $setting) {
                    $fields = json_decode($setting->fields, true);

                    if (! is_array($fields)) {
                        continue;
                    }

                    unset($fields['site_alert_enabled'], $fields['site_alert_message']);

                    DB::table('settings')->where('id', $setting->id)->update([
                        'fields' => json_encode($fields),
                        'updated_at' => now(),
                    ]);
                }
            });
    }
};
