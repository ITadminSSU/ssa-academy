<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('help_center_articles', function (Blueprint $table) {
            $table->string('video')->nullable()->after('video_url');
            $table->string('video_name')->nullable()->after('video');
        });
    }

    public function down(): void
    {
        Schema::table('help_center_articles', function (Blueprint $table) {
            $table->dropColumn(['video', 'video_name']);
        });
    }
};
