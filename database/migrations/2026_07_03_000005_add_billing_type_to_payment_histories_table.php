<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_histories', function (Blueprint $table) {
            $table->string('billing_type')->default('one_time')->after('payment_type');
            $table->foreignId('subscription_id')->nullable()->after('billing_type')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_histories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropColumn('billing_type');
        });
    }
};
