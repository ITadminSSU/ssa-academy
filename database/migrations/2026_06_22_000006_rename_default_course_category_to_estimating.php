<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $hasEstimating = DB::table('course_categories')->where('slug', 'estimating-course')->exists();

        if (!$hasEstimating) {
            DB::table('course_categories')
                ->where('slug', 'default')
                ->update([
                    'title' => 'Estimating Course',
                    'slug' => 'estimating-course',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        DB::table('course_categories')
            ->where('slug', 'estimating-course')
            ->update([
                'title' => 'Default',
                'slug' => 'default',
                'updated_at' => now(),
            ]);
    }
};
