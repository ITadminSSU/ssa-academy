<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('team_members') || ! Schema::hasColumn('team_members', 'photo')) {
            return;
        }

        $table = DB::getTablePrefix() . 'team_members';
        DB::statement("ALTER TABLE `{$table}` MODIFY `photo` TEXT NULL");
    }

    public function down(): void
    {
        if (! Schema::hasTable('team_members') || ! Schema::hasColumn('team_members', 'photo')) {
            return;
        }

        $table = DB::getTablePrefix() . 'team_members';
        DB::statement("ALTER TABLE `{$table}` MODIFY `photo` VARCHAR(255) NULL");
    }
};
