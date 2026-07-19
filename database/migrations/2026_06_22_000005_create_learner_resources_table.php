<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_resources', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('course_outline');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file')->nullable();
            $table->string('file_name')->nullable();
            $table->string('link')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_resources');
    }
};
