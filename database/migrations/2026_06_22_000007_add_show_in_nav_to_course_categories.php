<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('course_categories', 'show_in_nav')) {
            Schema::table('course_categories', function (Blueprint $table) {
                $table->boolean('show_in_nav')->default(false)->after('status');
            });
        }

        // Surface the existing Estimating Course in the learner sidebar.
        DB::table('course_categories')->where('slug', 'estimating-course')->update(['show_in_nav' => true]);

        // Create the Software Training category (flagged for the learner sidebar).
        $now = now();
        $exists = DB::table('course_categories')->where('slug', 'software-training')->exists();

        if (!$exists) {
            $maxSort = (int) DB::table('course_categories')->max('sort');

            DB::table('course_categories')->insert([
                'title' => 'Software Training',
                'slug' => 'software-training',
                'status' => 1,
                'show_in_nav' => true,
                'sort' => $maxSort + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('course_categories')->where('slug', 'software-training')->delete();

        if (Schema::hasColumn('course_categories', 'show_in_nav')) {
            Schema::table('course_categories', function (Blueprint $table) {
                $table->dropColumn('show_in_nav');
            });
        }
    }
};
