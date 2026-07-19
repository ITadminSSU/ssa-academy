<?php

use App\Support\Branding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $legacySlogans = [
        'Enterprise training for teams and professionals',
        'Enterprise training for internal teams and external professionals.',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        $tagline = Branding::tagline();

        DB::table('settings')->orderBy('id')->chunkById(50, function ($settings) use ($tagline) {
            foreach ($settings as $setting) {
                $fields = json_decode($setting->fields, true);

                if (!is_array($fields) || $setting->type !== 'system' || !isset($fields['slogan'])) {
                    continue;
                }

                $slogan = trim((string) $fields['slogan']);

                if ($slogan === $tagline) {
                    continue;
                }

                if (!in_array($slogan, $this->legacySlogans, true) && str_contains(strtolower($slogan), 'construction industry')) {
                    continue;
                }

                if (in_array($slogan, $this->legacySlogans, true) || str_contains(strtolower($slogan), 'enterprise training for teams and professionals')) {
                    $fields['slogan'] = $tagline;

                    DB::table('settings')->where('id', $setting->id)->update([
                        'fields' => json_encode($fields),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Tagline copy update is not reverted automatically.
    }
};
