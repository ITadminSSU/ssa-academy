<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('courses') || Schema::hasColumn('courses', 'choice_best_value')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->string('choice_best_value', 20)->default('recurring')->after('choice_recurring_compare');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('courses') || ! Schema::hasColumn('courses', 'choice_best_value')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('choice_best_value');
        });
    }
};
