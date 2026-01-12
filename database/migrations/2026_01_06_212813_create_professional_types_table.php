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
        if (!Schema::hasTable('professional_types')) {
        Schema::create('professional_types', function (Blueprint $table) {
            $table->id();
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
        } else {
            // If table exists, check and add missing columns
            Schema::table('professional_types', function (Blueprint $table) {
                if (!Schema::hasColumn('professional_types', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('name');
                }
                if (!Schema::hasColumn('professional_types', 'sort_order')) {
                    $table->integer('sort_order')->default(0)->after('is_active');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professional_types');
    }
};
