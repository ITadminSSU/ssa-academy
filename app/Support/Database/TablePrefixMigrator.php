<?php

namespace App\Support\Database;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TablePrefixMigrator
{
    /**
     * Rename legacy mentor_lms_* or unprefixed tables to ssu_academy_*.
     *
     * @return array{renamed: list<array{from: string, to: string}>, skipped: list<string>}
     */
    public function migrate(?string $connectionName = null): array
    {
        $connection = DB::connection($connectionName);
        $schema = Schema::connection($connection->getName());
        $originalPrefix = $connection->getTablePrefix();

        $connection->setTablePrefix('');

        $renamed = [];
        $skipped = [];

        $schema->disableForeignKeyConstraints();

        try {
            $existingTables = $this->listPhysicalTables($connection);

            foreach (SsuAcademyTableRegistry::tables() as $logicalName) {
                $target = SsuAcademyTableRegistry::TARGET_PREFIX . $logicalName;
                $legacy = SsuAcademyTableRegistry::LEGACY_PREFIX . $logicalName;
                $unprefixed = $logicalName;

                if (in_array($target, $existingTables, true)) {
                    $skipped[] = $target;
                    continue;
                }

                $source = null;

                if (in_array($legacy, $existingTables, true)) {
                    $source = $legacy;
                } elseif (in_array($unprefixed, $existingTables, true)) {
                    $source = $unprefixed;
                }

                if ($source === null) {
                    continue;
                }

                $schema->rename($source, $target);
                $renamed[] = ['from' => $source, 'to' => $target];
                $existingTables = array_values(array_diff($existingTables, [$source]));
                $existingTables[] = $target;
            }
        } finally {
            $schema->enableForeignKeyConstraints();
            $connection->setTablePrefix($originalPrefix);
        }

        return [
            'renamed' => $renamed,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return list<string>
     */
    private function listPhysicalTables(Connection $connection): array
    {
        return match ($connection->getDriverName()) {
            'sqlite' => collect($connection->select(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
            ))->pluck('name')->all(),
            'mysql', 'mariadb' => collect($connection->select(
                'SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
                [$connection->getDatabaseName()]
            ))->pluck('name')->all(),
            'pgsql' => collect($connection->select(
                "SELECT tablename AS name FROM pg_catalog.pg_tables WHERE schemaname = 'public'"
            ))->pluck('name')->all(),
            default => [],
        };
    }
}
