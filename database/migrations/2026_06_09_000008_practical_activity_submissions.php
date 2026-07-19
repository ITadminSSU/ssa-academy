<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('section_lessons', function (Blueprint $table) {
            $table->boolean('requires_submission')->default(false)->after('lesson_type');
            $table->unsignedInteger('activity_total_mark')->nullable()->after('requires_submission');
            $table->unsignedInteger('activity_pass_mark')->nullable()->after('activity_total_mark');
            $table->unsignedInteger('activity_retake')->default(1)->after('activity_pass_mark');
        });

        Schema::create('lesson_activity_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('attachment_type')->default('file');
            $table->string('attachment_path');
            $table->text('comment')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->decimal('marks_obtained', 8, 2)->nullable();
            $table->text('instructor_feedback')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempt_number')->default(1);
            $table->foreignId('section_lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_activity_submissions');

        Schema::table('section_lessons', function (Blueprint $table) {
            $table->dropColumn([
                'requires_submission',
                'activity_total_mark',
                'activity_pass_mark',
                'activity_retake',
            ]);
        });
    }
};
