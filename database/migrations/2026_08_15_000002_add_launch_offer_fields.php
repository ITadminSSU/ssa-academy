<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('launch_offer_enabled')->default(false)->after('subscription_price');
            $table->timestamp('launch_offer_starts_at')->nullable()->after('launch_offer_enabled');
            $table->timestamp('launch_offer_ends_at')->nullable()->after('launch_offer_starts_at');
            $table->decimal('launch_list_price', 10, 2)->nullable()->after('launch_offer_ends_at');
            $table->decimal('launch_offer_price', 10, 2)->nullable()->after('launch_list_price');
            $table->decimal('launch_deposit_amount', 10, 2)->nullable()->after('launch_offer_price');
            $table->decimal('launch_balance_amount', 10, 2)->nullable()->after('launch_deposit_amount');
            $table->unsignedSmallInteger('launch_balance_grace_days')->default(5)->after('launch_balance_amount');
            $table->timestamp('launch_subscription_trial_ends_at')->nullable()->after('launch_balance_grace_days');
            $table->decimal('launch_full_upfront_price', 10, 2)->nullable()->after('launch_subscription_trial_ends_at');
        });

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->decimal('deposit_amount', 10, 2)->nullable()->after('subscription_id');
            $table->timestamp('deposit_paid_at')->nullable()->after('deposit_amount');
            $table->unsignedBigInteger('deposit_payment_history_id')->nullable()->after('deposit_paid_at');
            $table->decimal('balance_amount', 10, 2)->nullable()->after('deposit_payment_history_id');
            $table->timestamp('balance_due_at')->nullable()->after('balance_amount');
            $table->timestamp('balance_deadline_at')->nullable()->after('balance_due_at');
            $table->timestamp('balance_paid_at')->nullable()->after('balance_deadline_at');
            $table->unsignedBigInteger('balance_payment_history_id')->nullable()->after('balance_paid_at');
            $table->timestamp('forfeited_at')->nullable()->after('balance_payment_history_id');
            $table->string('launch_offer_cohort', 50)->nullable()->after('forfeited_at');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'launch_offer_enabled',
                'launch_offer_starts_at',
                'launch_offer_ends_at',
                'launch_list_price',
                'launch_offer_price',
                'launch_deposit_amount',
                'launch_balance_amount',
                'launch_balance_grace_days',
                'launch_subscription_trial_ends_at',
                'launch_full_upfront_price',
            ]);
        });

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropColumn([
                'deposit_amount',
                'deposit_paid_at',
                'deposit_payment_history_id',
                'balance_amount',
                'balance_due_at',
                'balance_deadline_at',
                'balance_paid_at',
                'balance_payment_history_id',
                'forfeited_at',
                'launch_offer_cohort',
            ]);
        });
    }
};
