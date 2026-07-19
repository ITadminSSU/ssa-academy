<?php

use App\Support\Branding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $legacyDescription = 'Smart Sourcing Academy is the enterprise learning platform for employee training, certifications, assignments, and talent development.';

    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        $description = Branding::description();

        DB::table('settings')->orderBy('id')->chunkById(50, function ($settings) use ($description) {
            foreach ($settings as $setting) {
                if ($setting->type !== 'system') {
                    continue;
                }

                $fields = json_decode($setting->fields, true);

                if (!is_array($fields) || !isset($fields['description'])) {
                    continue;
                }

                $current = trim((string) $fields['description']);

                if ($current === $description) {
                    continue;
                }

                if ($current !== $this->legacyDescription && !str_contains(strtolower($current), 'enterprise learning platform')) {
                    continue;
                }

                $fields['description'] = $description;

                DB::table('settings')->where('id', $setting->id)->update([
                    'fields' => json_encode($fields),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->orderBy('id')->chunkById(50, function ($settings) {
            foreach ($settings as $setting) {
                if ($setting->type !== 'system') {
                    continue;
                }

                $fields = json_decode($setting->fields, true);

                if (!is_array($fields) || !isset($fields['description'])) {
                    continue;
                }

                if ($fields['description'] !== Branding::description()) {
                    continue;
                }

                $fields['description'] = $this->legacyDescription;

                DB::table('settings')->where('id', $setting->id)->update([
                    'fields' => json_encode($fields),
                    'updated_at' => now(),
                ]);
            }
        });
    }
};
