<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->decimal('choice_one_time_compare', 10, 2)->nullable()->after('installment_months');
            $table->decimal('choice_recurring_compare', 10, 2)->nullable()->after('choice_one_time_compare');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('choice_installment_completed_at')->nullable()->after('installment_paid_count');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('choice_installment_completed_at');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['choice_one_time_compare', 'choice_recurring_compare']);
        });
    }
};
