<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('course_forum_replies')) {
            return;
        }

        Schema::table('course_forum_replies', function (Blueprint $table) {
            $table->dropForeign(['course_forum_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('course_forum_replies', function (Blueprint $table) {
            $table->foreign('course_forum_id')
                ->references('id')
                ->on('course_forums')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('course_forum_replies')) {
            return;
        }

        Schema::table('course_forum_replies', function (Blueprint $table) {
            $table->dropForeign(['course_forum_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('course_forum_replies', function (Blueprint $table) {
            $table->foreign('course_forum_id')
                ->references('id')
                ->on('course_forums');

            $table->foreign('user_id')
                ->references('id')
                ->on('users');
        });
    }
};
