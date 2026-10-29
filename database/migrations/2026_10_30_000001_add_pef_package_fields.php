<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('pef_tagged')->default(false)->after('user_type');
            $table->timestamp('pef_tagged_at')->nullable()->after('pef_tagged');
            $table->timestamp('pef_access_ends_at')->nullable()->after('pef_tagged_at');
        });

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->boolean('pef_granted')->default(false)->after('enrollment_type');
        });
    }

    public function down(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropColumn('pef_granted');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pef_tagged', 'pef_tagged_at', 'pef_access_ends_at']);
        });
    }
};
