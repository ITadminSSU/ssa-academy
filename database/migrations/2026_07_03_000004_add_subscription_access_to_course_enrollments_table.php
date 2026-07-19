<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->string('access_status')->default('active')->after('enrollment_type');
            $table->timestamp('suspended_at')->nullable()->after('expiry_date');
            $table->foreignId('subscription_id')->nullable()->after('suspended_at')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropColumn(['access_status', 'suspended_at']);
        });
    }
};
