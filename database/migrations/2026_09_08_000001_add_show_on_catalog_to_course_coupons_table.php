<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_coupons')) {
            return;
        }

        Schema::table('course_coupons', function (Blueprint $table) {
            if (! Schema::hasColumn('course_coupons', 'show_on_catalog')) {
                $table->boolean('show_on_catalog')->default(false)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('course_coupons') || ! Schema::hasColumn('course_coupons', 'show_on_catalog')) {
            return;
        }

        Schema::table('course_coupons', function (Blueprint $table) {
            $table->dropColumn('show_on_catalog');
        });
    }
};
