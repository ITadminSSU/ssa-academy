<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'legal_agreement_version')) {
            return;
        }

        $table = DB::getTablePrefix().'users';

        // Was varchar(32); SignWell versions are "signwell:{uuid}" (~45 chars).
        DB::statement("ALTER TABLE `{$table}` MODIFY `legal_agreement_version` VARCHAR(100) NULL");
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'legal_agreement_version')) {
            return;
        }

        $table = DB::getTablePrefix().'users';

        DB::statement("ALTER TABLE `{$table}` MODIFY `legal_agreement_version` VARCHAR(32) NULL");
    }
};
