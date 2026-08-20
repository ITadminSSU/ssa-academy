<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('dashboard_first_visited_at')->nullable()->after('email_verified_at');
        });

        // Existing accounts already use the dashboard — treat them as returning visitors.
        DB::table('users')
            ->whereNull('dashboard_first_visited_at')
            ->update(['dashboard_first_visited_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dashboard_first_visited_at');
        });
    }
};
