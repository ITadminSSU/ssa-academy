<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('video_copy_status', 32)->nullable()->after('status');
            $table->text('video_copy_error')->nullable()->after('video_copy_status');
        });

        Schema::create('course_lesson_video_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('section_lesson_id')->constrained('section_lessons')->cascadeOnDelete();
            $table->string('source_bunny_video_id');
            $table->string('status')->default('pending');
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_lesson_video_copies');

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['video_copy_status', 'video_copy_error']);
        });
    }
};
