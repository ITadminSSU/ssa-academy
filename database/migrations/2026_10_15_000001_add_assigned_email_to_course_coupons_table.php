<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_coupons') || Schema::hasColumn('course_coupons', 'assigned_email')) {
            return;
        }

        Schema::table('course_coupons', function (Blueprint $table) {
            if (Schema::hasColumn('course_coupons', 'show_on_catalog')) {
                $table->string('assigned_email')->nullable()->after('show_on_catalog');
            } else {
                $table->string('assigned_email')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('course_coupons') || ! Schema::hasColumn('course_coupons', 'assigned_email')) {
            return;
        }

        Schema::table('course_coupons', function (Blueprint $table) {
            $table->dropColumn('assigned_email');
        });
    }
};
