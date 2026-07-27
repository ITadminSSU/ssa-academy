<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('legal_confirmation_email_sent_at')->nullable()->after('legal_agreement_ip');
            $table->text('legal_confirmation_email_last_error')->nullable()->after('legal_confirmation_email_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'legal_confirmation_email_sent_at',
                'legal_confirmation_email_last_error',
            ]);
        });
    }
};
