<?php

use App\Support\LandingOverlay;
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

        $homeSetting = DB::table('settings')->where('type', 'home_page')->first();

        if (! $homeSetting) {
            return;
        }

        $fields = json_decode((string) $homeSetting->fields, true);
        $fields = is_array($fields) ? $fields : [];
        $defaults = LandingOverlay::defaults();

        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $fields)) {
                $fields[$key] = $value;
            }
        }

        DB::table('settings')->where('id', $homeSetting->id)->update([
            'fields' => json_encode($fields),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $homeSetting = DB::table('settings')->where('type', 'home_page')->first();

        if (! $homeSetting) {
            return;
        }

        $fields = json_decode((string) $homeSetting->fields, true);
        $fields = is_array($fields) ? $fields : [];

        foreach (array_keys(LandingOverlay::defaults()) as $key) {
            unset($fields[$key]);
        }

        DB::table('settings')->where('id', $homeSetting->id)->update([
            'fields' => json_encode($fields),
            'updated_at' => now(),
        ]);
    }
};
