<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'professional_type_id')) {
                $table->unsignedBigInteger('professional_type_id')->nullable()->after('instructor_id');
            }
            if (!Schema::hasColumn('users', 'professional_type_other')) {
                $table->string('professional_type_other')->nullable()->after('professional_type_id');
            }
        });

        // Add foreign key constraint only if professional_types table exists and column exists
        if (Schema::hasTable('professional_types') && Schema::hasColumn('users', 'professional_type_id')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->foreign('professional_type_id')->references('id')->on('professional_types')->onDelete('set null');
                });
            } catch (\Exception $e) {
                // Foreign key already exists or column type mismatch, skip
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Try to drop foreign key - it will fail silently if it doesn't exist
            try {
                $table->dropForeign(['professional_type_id']);
            } catch (\Exception $e) {
                // Foreign key doesn't exist, continue
            }
            
            if (Schema::hasColumn('users', 'professional_type_id')) {
                $table->dropColumn(['professional_type_id']);
            }
            if (Schema::hasColumn('users', 'professional_type_other')) {
                $table->dropColumn(['professional_type_other']);
            }
        });
    }
};
