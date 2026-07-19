<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('legal_agreement_accepted_at')->nullable()->after('email_verified_at');
            $table->string('legal_agreement_version', 32)->nullable()->after('legal_agreement_accepted_at');
            $table->string('legal_agreement_ip', 45)->nullable()->after('legal_agreement_version');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'legal_agreement_accepted_at',
                'legal_agreement_version',
                'legal_agreement_ip',
            ]);
        });
    }
};
