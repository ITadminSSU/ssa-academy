<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('page_sections') || ! Schema::hasColumn('page_sections', 'video_url')) {
            return;
        }

        // Avoid ->change() which requires doctrine/dbal on Forge.
        $table = DB::getTablePrefix() . 'page_sections';
        DB::statement("ALTER TABLE `{$table}` MODIFY `video_url` TEXT NULL");
    }

    public function down(): void
    {
        if (! Schema::hasTable('page_sections') || ! Schema::hasColumn('page_sections', 'video_url')) {
            return;
        }

        $table = DB::getTablePrefix() . 'page_sections';
        DB::statement("ALTER TABLE `{$table}` MODIFY `video_url` VARCHAR(255) NULL");
    }
};
