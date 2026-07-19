<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_refund_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_history_id')
                ->constrained(table: 'payment_histories', indexName: 'ssu_pgra_ph_fk')
                ->cascadeOnDelete();
            $table->foreignId('initiated_by_user_id')
                ->constrained(table: 'users', indexName: 'ssu_pgra_user_fk')
                ->cascadeOnDelete();
            $table->string('gateway');
            $table->string('transaction_id')->nullable();
            $table->boolean('success')->default(false);
            $table->string('gateway_refund_id')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_refund_attempts');
    }
};
