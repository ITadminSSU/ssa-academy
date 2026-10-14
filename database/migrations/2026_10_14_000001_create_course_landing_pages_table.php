<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_landing_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('countdown_ends_at')->nullable();
            $table->text('hero_photo')->nullable();
            $table->string('video_url', 2048)->nullable();
            $table->string('video_duration', 20)->nullable();
            $table->string('coach_line')->nullable();
            $table->longText('embed_html')->nullable();
            $table->json('reviews')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_landing_pages');
    }
};
