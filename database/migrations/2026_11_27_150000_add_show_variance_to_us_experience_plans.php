<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('us_experience_plans') || Schema::hasColumn('us_experience_plans', 'show_variance')) {
            return;
        }

        Schema::table('us_experience_plans', function (Blueprint $table) {
            $table->boolean('show_variance')->default(true);
        });

        if (! Schema::hasTable('courses') || ! Schema::hasColumn('courses', 'show_us_experience_variance')) {
            return;
        }

        $hiddenCourseIds = DB::table('courses')
            ->where('show_us_experience_variance', false)
            ->pluck('id');

        if ($hiddenCourseIds->isEmpty()) {
            return;
        }

        DB::table('us_experience_plans')
            ->whereIn('course_id', $hiddenCourseIds)
            ->update(['show_variance' => false]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('us_experience_plans') || ! Schema::hasColumn('us_experience_plans', 'show_variance')) {
            return;
        }

        Schema::table('us_experience_plans', function (Blueprint $table) {
            $table->dropColumn('show_variance');
        });
    }
};
