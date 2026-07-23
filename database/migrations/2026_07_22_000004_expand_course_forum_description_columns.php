<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('course_forums') && Schema::hasColumn('course_forums', 'description')) {
            Schema::table('course_forums', function (Blueprint $table) {
                $table->longText('description')->change();
            });
        }

        if (Schema::hasTable('course_forum_replies') && Schema::hasColumn('course_forum_replies', 'description')) {
            Schema::table('course_forum_replies', function (Blueprint $table) {
                $table->longText('description')->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('course_forums') && Schema::hasColumn('course_forums', 'description')) {
            Schema::table('course_forums', function (Blueprint $table) {
                $table->text('description')->change();
            });
        }

        if (Schema::hasTable('course_forum_replies') && Schema::hasColumn('course_forum_replies', 'description')) {
            Schema::table('course_forum_replies', function (Blueprint $table) {
                $table->text('description')->change();
            });
        }
    }
};
