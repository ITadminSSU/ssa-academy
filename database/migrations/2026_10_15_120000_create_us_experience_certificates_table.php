<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('us_experience_certificates')) {
            return;
        }

        Schema::create('us_experience_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('group_name');
            $table->string('identifier')->unique();
            $table->string('certificate_id')->nullable()->unique();
            $table->string('verification_reference')->nullable()->unique();
            $table->timestamps();

            $table->unique(['user_id', 'course_id', 'group_name'], 'us_experience_certificates_user_course_group_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('us_experience_certificates');
    }
};
