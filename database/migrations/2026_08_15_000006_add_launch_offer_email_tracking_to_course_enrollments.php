<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->timestamp('deposit_confirmation_sent_at')->nullable()->after('launch_offer_cohort');
            $table->timestamp('balance_due_notice_sent_at')->nullable()->after('deposit_confirmation_sent_at');
            $table->timestamp('balance_mid_reminder_sent_at')->nullable()->after('balance_due_notice_sent_at');
            $table->timestamp('balance_final_reminder_sent_at')->nullable()->after('balance_mid_reminder_sent_at');
            $table->timestamp('balance_paid_confirmation_sent_at')->nullable()->after('balance_final_reminder_sent_at');
            $table->timestamp('forfeit_notice_sent_at')->nullable()->after('balance_paid_confirmation_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropColumn([
                'deposit_confirmation_sent_at',
                'balance_due_notice_sent_at',
                'balance_mid_reminder_sent_at',
                'balance_final_reminder_sent_at',
                'balance_paid_confirmation_sent_at',
                'forfeit_notice_sent_at',
            ]);
        });
    }
};
