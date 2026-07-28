<?php

use App\Support\Branding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('certificate_templates')) {
            return;
        }

        $logoPath = Branding::logo('certificate') ?? Branding::logo('dark');

        if (!$logoPath) {
            return;
        }

        DB::table('certificate_templates')
            ->whereIn('type', ['course', 'exam'])
            ->update([
                'logo_path' => $logoPath,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Certificate logo migration is intentionally not reversible.
    }
};
