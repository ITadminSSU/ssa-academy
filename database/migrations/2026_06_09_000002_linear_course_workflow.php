<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watch_histories', function (Blueprint $table) {
            $table->json('lesson_watch_progress')->nullable()->after('completed_watching');
        });

        Schema::table('course_assignments', function (Blueprint $table) {
            $table->string('sample_project_type')->nullable()->after('summary');
            $table->string('sample_project_path')->nullable()->after('sample_project_type');
        });

        Schema::create('assignment_sample_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_assignment_id')->constrained()->cascadeOnDelete();
            $table->timestamp('downloaded_at');
            $table->timestamps();

            $table->unique(['user_id', 'course_assignment_id'], 'asn_sample_dl_user_asn');
        });

        Schema::table('course_certificates', function (Blueprint $table) {
            $table->string('verification_reference')->nullable()->unique()->after('identifier');
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->string('tracking_reference')->nullable()->unique()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn('tracking_reference');
        });

        Schema::table('course_certificates', function (Blueprint $table) {
            $table->dropColumn('verification_reference');
        });

        Schema::dropIfExists('assignment_sample_downloads');

        Schema::table('course_assignments', function (Blueprint $table) {
            $table->dropColumn(['sample_project_type', 'sample_project_path']);
        });

        Schema::table('watch_histories', function (Blueprint $table) {
            $table->dropColumn('lesson_watch_progress');
        });
    }
};
