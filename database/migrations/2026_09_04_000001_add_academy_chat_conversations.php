<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_conversations')) {
            return;
        }

        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
        });

        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('course_id')->nullable()->change();
            $table->foreign('course_id')->references('id')->on('courses')->nullOnDelete();

            if (! Schema::hasColumn('chat_conversations', 'academy_key')) {
                $table->string('academy_key', 40)->nullable()->unique()->after('student_user_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('chat_conversations')) {
            return;
        }

        Schema::table('chat_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('chat_conversations', 'academy_key')) {
                $table->dropUnique(['academy_key']);
                $table->dropColumn('academy_key');
            }

            $table->dropForeign(['course_id']);
        });

        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('course_id')->nullable(false)->change();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }
};
