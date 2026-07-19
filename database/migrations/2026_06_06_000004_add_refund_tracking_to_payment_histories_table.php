<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_histories', function (Blueprint $table) {
            $table->string('refund_status')->default('paid')->after('meta');
            $table->text('refund_notes')->nullable()->after('refund_status');
        });

        DB::table('payment_histories')->whereNull('refund_status')->update(['refund_status' => 'paid']);
    }

    public function down(): void
    {
        Schema::table('payment_histories', function (Blueprint $table) {
            $table->dropColumn(['refund_status', 'refund_notes']);
        });
    }
};
