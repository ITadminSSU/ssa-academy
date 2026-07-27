<?php

use App\Support\Branding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $legacyDescriptions = [
        'Enterprise training for teams and professionals within the construction industry.',
        'Enterprise training for teams and professionals',
        'Enterprise training for internal teams and external professionals.',
    ];

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

                if (!in_array($current, $this->legacyDescriptions, true)
                    && !str_contains(strtolower($current), 'enterprise training for teams and professionals')) {
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
        // Footer description copy update is not reverted automatically.
    }
};
