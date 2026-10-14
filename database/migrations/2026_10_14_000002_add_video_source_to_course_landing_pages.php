<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_landing_pages') || Schema::hasColumn('course_landing_pages', 'video_source')) {
            return;
        }

        Schema::table('course_landing_pages', function (Blueprint $table) {
            $table->string('video_source', 16)->default('link')->after('video_url');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('course_landing_pages') || ! Schema::hasColumn('course_landing_pages', 'video_source')) {
            return;
        }

        Schema::table('course_landing_pages', function (Blueprint $table) {
            $table->dropColumn('video_source');
        });
    }
};
