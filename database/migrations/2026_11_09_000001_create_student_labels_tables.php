<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->string('color', 7);
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('student_label_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_label_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'student_label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_label_user');
        Schema::dropIfExists('student_labels');
    }
};
