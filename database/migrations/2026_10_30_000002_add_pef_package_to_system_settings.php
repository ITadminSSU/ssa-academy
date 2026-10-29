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

                    if (! array_key_exists('pef_enabled', $fields)) {
                        $fields['pef_enabled'] = false;
                    }

                    if (! array_key_exists('pef_course_ids', $fields)) {
                        $fields['pef_course_ids'] = [];
                    }

                    if (! array_key_exists('pef_access_duration', $fields)) {
                        $fields['pef_access_duration'] = '2 years';
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

                    unset($fields['pef_enabled'], $fields['pef_course_ids'], $fields['pef_access_duration']);

                    DB::table('settings')->where('id', $setting->id)->update([
                        'fields' => json_encode($fields),
                        'updated_at' => now(),
                    ]);
                }
            });
    }
};
