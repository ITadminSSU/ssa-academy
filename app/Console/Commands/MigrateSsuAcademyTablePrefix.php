<?php

namespace App\Console\Commands;

use App\Support\Database\TablePrefixMigrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateSsuAcademyTablePrefix extends Command
{
    protected $signature = 'ssu:migrate-table-prefix {--connection= : Database connection name}';

    protected $description = 'Rename mentor_lms_* or unprefixed tables to ssu_academy_* without breaking foreign keys';

    public function handle(TablePrefixMigrator $migrator): int
    {
        $connectionName = $this->option('connection');

        if ($connectionName) {
            DB::setDefaultConnection($connectionName);
        }

        $connection = DB::connection($connectionName);
        $hadPrefix = $connection->getTablePrefix() !== '';

        if ($hadPrefix) {
            $this->warn('Temporarily clearing DB_TABLE_PREFIX so physical tables can be renamed.');
            $connection->setTablePrefix('');
        }

        $result = $migrator->migrate($connectionName);

        if ($hadPrefix) {
            $connection->setTablePrefix(config('database.connections.' . $connection->getName() . '.prefix', ''));
        }

        if ($result['renamed'] === []) {
            $this->info('No tables required renaming. Schema already uses ssu_academy_* names or is empty.');
        } else {
            foreach ($result['renamed'] as $rename) {
                $this->line("Renamed {$rename['from']} -> {$rename['to']}");
            }

            $this->info('Table prefix migration complete.');
        }

        if (config('database.connections.' . $connection->getName() . '.prefix', '') === '') {
            $this->newLine();
            $this->comment('Add DB_TABLE_PREFIX=ssu_academy_ to your .env file, then run: php artisan config:clear');
        }

        return self::SUCCESS;
    }
}
