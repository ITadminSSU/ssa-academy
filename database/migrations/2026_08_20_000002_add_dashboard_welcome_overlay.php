<?php

use App\Support\DashboardWelcomeOverlay;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('dashboard_welcome_overlay_dismissed_version', 64)
                ->nullable()
                ->after('email_verified_at');
        });

        if (! DB::table('settings')->where('type', 'dashboard_welcome_overlay')->exists()) {
            DB::table('settings')->insert([
                'type' => 'dashboard_welcome_overlay',
                'sub_type' => 'default',
                'title' => 'Dashboard welcome overlay',
                'fields' => json_encode(DashboardWelcomeOverlay::defaults()),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dashboard_welcome_overlay_dismissed_version');
        });

        DB::table('settings')->where('type', 'dashboard_welcome_overlay')->delete();
    }
};
