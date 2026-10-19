<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('instructor_card_name', 255)->nullable()->after('hero_video');
            $table->text('instructor_card_biography')->nullable()->after('instructor_card_name');
            $table->text('instructor_card_photo')->nullable()->after('instructor_card_biography');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'instructor_card_name',
                'instructor_card_biography',
                'instructor_card_photo',
            ]);
        });
    }
};
