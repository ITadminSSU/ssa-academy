<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quiz_questions')) {
            return;
        }

        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->text('title')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('quiz_questions')) {
            return;
        }

        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->string('title')->change();
        });
    }
};
