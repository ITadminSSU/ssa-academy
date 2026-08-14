<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Course / category media columns were VARCHAR(255). Private R2 temporary URLs
 * (with AWS4 query strings) exceed that length and cause:
 * SQLSTATE[22001] Data too long for column 'thumbnail'
 *
 * Code now stores unsigned object URLs; TEXT is a safety net for any legacy rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->widen('courses', ['thumbnail', 'banner', 'preview']);
        $this->widen('course_categories', ['thumbnail']);
        $this->widen('section_lessons', ['thumbnail']);
    }

    public function down(): void
    {
        $this->narrow('courses', ['thumbnail', 'banner', 'preview']);
        $this->narrow('course_categories', ['thumbnail']);
        $this->narrow('section_lessons', ['thumbnail']);
    }

    /**
     * @param  list<string>  $columns
     */
    private function widen(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $physical = DB::getTablePrefix().$table;

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::statement("ALTER TABLE `{$physical}` MODIFY `{$column}` TEXT NULL");
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function narrow(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $physical = DB::getTablePrefix().$table;

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::statement("ALTER TABLE `{$physical}` MODIFY `{$column}` VARCHAR(255) NULL");
        }
    }
};
