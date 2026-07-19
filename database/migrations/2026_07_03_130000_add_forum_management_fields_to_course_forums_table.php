<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('course_forums')) {
            return;
        }

        Schema::table('course_forums', function (Blueprint $table) {
            if (!Schema::hasColumn('course_forums', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('section_lesson_id');
            }

            if (!Schema::hasColumn('course_forums', 'resolved_by')) {
                $table->foreignId('resolved_by')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('course_forums', 'pinned_reply_id')) {
                $table->foreignId('pinned_reply_id')->nullable()->after('resolved_by')->constrained('course_forum_replies')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('course_forums')) {
            return;
        }

        Schema::table('course_forums', function (Blueprint $table) {
            if (Schema::hasColumn('course_forums', 'pinned_reply_id')) {
                $table->dropConstrainedForeignId('pinned_reply_id');
            }

            if (Schema::hasColumn('course_forums', 'resolved_by')) {
                $table->dropConstrainedForeignId('resolved_by');
            }

            if (Schema::hasColumn('course_forums', 'resolved_at')) {
                $table->dropColumn('resolved_at');
            }
        });
    }
};
