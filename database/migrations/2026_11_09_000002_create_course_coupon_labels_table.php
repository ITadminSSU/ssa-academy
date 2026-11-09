<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_coupon_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->string('color', 7);
            $table->timestamps();

            $table->unique('name');
        });

        Schema::table('course_coupons', function (Blueprint $table) {
            $table->foreignId('course_coupon_label_id')
                ->nullable()
                ->after('course_id')
                ->constrained('course_coupon_labels')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('course_coupons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('course_coupon_label_id');
        });

        Schema::dropIfExists('course_coupon_labels');
    }
};
