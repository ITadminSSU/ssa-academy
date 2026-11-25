<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('courses') || Schema::hasColumn('courses', 'show_us_experience_variance')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('show_us_experience_variance')->default(true);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('courses') || ! Schema::hasColumn('courses', 'show_us_experience_variance')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('show_us_experience_variance');
        });
    }
};
