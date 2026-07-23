<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('allow_staff_preview')->default(true)->after('launch_at');
            $table->boolean('allow_internal_preview')->default(false)->after('allow_staff_preview');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['allow_staff_preview', 'allow_internal_preview']);
        });
    }
};
