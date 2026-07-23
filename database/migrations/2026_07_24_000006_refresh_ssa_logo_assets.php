<?php

use App\Support\Branding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')
            ->where('type', 'system')
            ->orderBy('id')
            ->chunkById(20, function ($settings) {
                foreach ($settings as $setting) {
                    $fields = json_decode($setting->fields, true);

                    if (!is_array($fields)) {
                        continue;
                    }

                    $fields['logo_dark'] = Branding::logo('dark');
                    $fields['logo_light'] = Branding::logo('light');
                    $fields['favicon'] = Branding::logo('favicon') ?? Branding::logo('icon');

                    DB::table('settings')->where('id', $setting->id)->update([
                        'fields' => json_encode($fields),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Logo asset migration is intentionally not reversible.
    }
};
