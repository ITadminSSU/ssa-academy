<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('us_experience_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('group_name');
            $table->text('group_description')->nullable();
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('pass_mark')->default(85);
            $table->unsignedInteger('max_attempts')->default(10);
            $table->boolean('published')->default(false);
            $table->json('drawings')->nullable();
            $table->string('blank_template_file_url')->nullable();
            $table->string('blank_template_file_name')->nullable();
            $table->string('answer_key_file_url')->nullable();
            $table->string('answer_key_file_name')->nullable();
            $table->json('line_items')->nullable();
            $table->timestamp('parsed_at')->nullable();
            $table->string('tutorial_video_url')->nullable();
            $table->string('tutorial_video_name')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'sort_order']);
        });

        Schema::create('us_experience_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('us_experience_plan_id')->constrained('us_experience_plans')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('takeoff_pdf_url');
            $table->string('takeoff_pdf_name')->nullable();
            $table->string('boq_xlsx_url');
            $table->string('boq_xlsx_name')->nullable();
            $table->json('answer_data')->nullable();
            $table->decimal('marks_obtained', 8, 2)->nullable();
            $table->unsignedInteger('lines_correct')->nullable();
            $table->unsignedInteger('lines_total')->nullable();
            $table->decimal('lines_percent', 8, 2)->nullable();
            $table->json('grading_breakdown')->nullable();
            $table->string('status');
            $table->timestamp('submitted_at')->nullable();
            $table->text('trainer_feedback')->nullable();
            $table->timestamps();

            $table->unique(['us_experience_plan_id', 'user_id', 'attempt_number'], 'us_experience_attempts_plan_user_attempt_unique');
            $table->index(['us_experience_plan_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('us_experience_attempts');
        Schema::dropIfExists('us_experience_plans');
    }
};
