<?php

use App\Support\PersonName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'given_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('given_name')->nullable()->after('name');
                $table->string('last_name')->nullable()->after('given_name');
            });
        }

        DB::table('users')
            ->whereNull('given_name')
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    [$given, $last] = PersonName::split((string) $user->name);

                    DB::table('users')
                        ->where('id', $user->id)
                        ->whereNull('given_name')
                        ->update([
                            'given_name' => $given,
                            'last_name' => $last !== '' ? $last : null,
                            'name' => PersonName::join($given, $last),
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'last_name')) {
                $table->dropColumn('last_name');
            }

            if (Schema::hasColumn('users', 'given_name')) {
                $table->dropColumn('given_name');
            }
        });
    }
};
