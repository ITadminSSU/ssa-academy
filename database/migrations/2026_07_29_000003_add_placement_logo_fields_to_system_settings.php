<?php

use App\Support\Branding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function defaultLogoSizes(): array
    {
        return [
            'navbar' => ['height' => 48, 'maxWidth' => 120],
            'footer' => ['height' => 96, 'maxWidth' => 280],
            'auth' => ['height' => 200, 'maxWidth' => 576],
            'dashboard' => ['height' => 112, 'maxWidth' => 240],
            'certificate' => ['height' => 80, 'maxWidth' => 200],
        ];
    }

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

                    $logoDark = $fields['logo_dark'] ?? Branding::logo('dark');
                    $logoLight = $fields['logo_light'] ?? Branding::logo('light');

                    $fields['logo_navbar'] = $fields['logo_navbar'] ?? $logoDark;
                    $fields['logo_footer'] = $fields['logo_footer'] ?? Branding::logo('footer') ?? $logoDark;
                    $fields['logo_auth'] = $fields['logo_auth'] ?? $logoLight;
                    $fields['logo_dashboard'] = $fields['logo_dashboard'] ?? $logoLight;
                    $fields['logo_certificate'] = $fields['logo_certificate'] ?? Branding::logo('certificate');

                    if (empty($fields['logo_sizes']) || !is_array($fields['logo_sizes'])) {
                        $fields['logo_sizes'] = $this->defaultLogoSizes();
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
        // Placement logo fields are intentionally not removed on rollback.
    }
};
