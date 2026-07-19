<?php

use App\Support\Database\TablePrefixMigrator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rename mentor_lms_* (or legacy unprefixed) tables to ssu_academy_*.
     * Idempotent: safe to run when tables are already prefixed.
     */
    public function up(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() === 'sqlite'
            && $connection->getDatabaseName() === ':memory:') {
            return;
        }

        app(TablePrefixMigrator::class)->migrate($connection->getName());
    }

    public function down(): void
    {
        $connection = DB::connection();
        $schema = \Illuminate\Support\Facades\Schema::connection($connection->getName());
        $originalPrefix = $connection->getTablePrefix();

        $connection->setTablePrefix('');

        $schema->disableForeignKeyConstraints();

        try {
            $existingTables = match ($connection->getDriverName()) {
                'sqlite' => collect($connection->select(
                    "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
                ))->pluck('name')->all(),
                default => collect($connection->select(
                    'SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
                    [$connection->getDatabaseName()]
                ))->pluck('name')->all(),
            };

            foreach (\App\Support\Database\SsuAcademyTableRegistry::tables() as $logicalName) {
                $prefixed = \App\Support\Database\SsuAcademyTableRegistry::TARGET_PREFIX . $logicalName;

                if (! in_array($prefixed, $existingTables, true)) {
                    continue;
                }

                $schema->rename($prefixed, $logicalName);
            }
        } finally {
            $schema->enableForeignKeyConstraints();
            $connection->setTablePrefix($originalPrefix);
        }
    }
};
