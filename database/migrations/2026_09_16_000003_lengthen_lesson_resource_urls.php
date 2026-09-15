<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lesson_resources') || ! Schema::hasColumn('lesson_resources', 'resource')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        $resources = DB::getTablePrefix().'lesson_resources';
        DB::statement("ALTER TABLE `{$resources}` MODIFY `resource` TEXT NULL");

        if (Schema::hasTable('chunked_uploads') && Schema::hasColumn('chunked_uploads', 'file_url')) {
            $uploads = DB::getTablePrefix().'chunked_uploads';
            DB::statement("ALTER TABLE `{$uploads}` MODIFY `file_url` TEXT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('lesson_resources') && Schema::hasColumn('lesson_resources', 'resource')) {
            $resources = DB::getTablePrefix().'lesson_resources';
            DB::statement("ALTER TABLE `{$resources}` MODIFY `resource` VARCHAR(255) NOT NULL");
        }

        if (Schema::hasTable('chunked_uploads') && Schema::hasColumn('chunked_uploads', 'file_url')) {
            $uploads = DB::getTablePrefix().'chunked_uploads';
            DB::statement("ALTER TABLE `{$uploads}` MODIFY `file_url` VARCHAR(255) NULL");
        }
    }
};
