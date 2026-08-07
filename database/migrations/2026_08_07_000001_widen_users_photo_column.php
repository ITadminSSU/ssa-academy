<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Optional hardening migration — never block deploys if the table/column is missing.
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'photo')) {
            return;
        }

        try {
            $table = $this->usersTable();
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE `{$table}` MODIFY photo TEXT NULL");
            } elseif ($driver === 'pgsql') {
                DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN photo TYPE TEXT");
            }
        } catch (\Throwable $exception) {
            Log::warning('Skipped widen_users_photo_column migration', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'photo')) {
            return;
        }

        try {
            $table = $this->usersTable();
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE `{$table}` MODIFY photo VARCHAR(255) NULL");
            } elseif ($driver === 'pgsql') {
                DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN photo TYPE VARCHAR(255)");
            }
        } catch (\Throwable $exception) {
            Log::warning('Skipped widen_users_photo_column rollback', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function usersTable(): string
    {
        return Schema::getConnection()->getTablePrefix().'users';
    }
};
