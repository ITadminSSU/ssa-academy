<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'estimating_software')) {
                $table->json('estimating_software')->nullable()->after('professional_type_other');
            }

            if (! Schema::hasColumn('users', 'estimating_software_other')) {
                $table->string('estimating_software_other')->nullable()->after('estimating_software');
            }

            if (! Schema::hasColumn('users', 'construction_experience')) {
                $table->string('construction_experience')->nullable()->after('estimating_software_other');
            }

            if (! Schema::hasColumn('users', 'worked_as_construction_va')) {
                $table->boolean('worked_as_construction_va')->nullable()->after('construction_experience');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            foreach (['estimating_software', 'estimating_software_other', 'construction_experience', 'worked_as_construction_va'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
