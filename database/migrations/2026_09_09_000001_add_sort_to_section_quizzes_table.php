<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('section_quizzes')) {
            return;
        }

        Schema::table('section_quizzes', function (Blueprint $table) {
            if (! Schema::hasColumn('section_quizzes', 'sort')) {
                $table->unsignedInteger('sort')->default(0)->after('title');
            }
        });

        if (! Schema::hasColumn('section_quizzes', 'sort') || ! Schema::hasTable('course_sections')) {
            return;
        }

        $sectionIds = DB::table('course_sections')->pluck('id');

        foreach ($sectionIds as $sectionId) {
            $lessonMax = (int) DB::table('section_lessons')
                ->where('course_section_id', $sectionId)
                ->max('sort');

            $quizzes = DB::table('section_quizzes')
                ->where('course_section_id', $sectionId)
                ->orderByDesc('created_at')
                ->pluck('id');

            $sort = $lessonMax;

            foreach ($quizzes as $quizId) {
                $sort++;
                DB::table('section_quizzes')->where('id', $quizId)->update(['sort' => $sort]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('section_quizzes') || ! Schema::hasColumn('section_quizzes', 'sort')) {
            return;
        }

        Schema::table('section_quizzes', function (Blueprint $table) {
            $table->dropColumn('sort');
        });
    }
};
