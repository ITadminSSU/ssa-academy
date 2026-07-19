<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('exams', 'exam_category_id')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->dropForeign(['exam_category_id']);
                $table->dropColumn('exam_category_id');
            });
        }

        Schema::dropIfExists('exam_categories');
    }

    public function down(): void
    {
        Schema::create('exam_categories', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->integer('sort')->default(0);
            $table->boolean('status')->default(true);
            $table->string('thumbnail')->nullable();
            $table->timestamps();
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->foreignId('exam_category_id')->nullable()->after('instructor_id')->constrained('exam_categories')->nullOnDelete();
        });
    }
};
