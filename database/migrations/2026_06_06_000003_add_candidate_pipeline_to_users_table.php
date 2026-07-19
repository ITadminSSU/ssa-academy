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
            $table->string('candidate_status')->nullable()->after('user_type');
            $table->text('candidate_notes')->nullable()->after('candidate_status');
            $table->timestamp('candidate_status_updated_at')->nullable()->after('candidate_notes');
        });

        DB::table('users')
            ->where('user_type', 'external')
            ->where('role', 'student')
            ->whereNull('candidate_status')
            ->update(['candidate_status' => 'new']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['candidate_status', 'candidate_notes', 'candidate_status_updated_at']);
        });
    }
};
