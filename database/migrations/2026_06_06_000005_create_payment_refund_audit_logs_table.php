<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_refund_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_history_id')
                ->constrained(table: 'payment_histories', indexName: 'ssu_pral_ph_fk')
                ->cascadeOnDelete();
            $table->foreignId('changed_by_user_id')
                ->constrained(table: 'users', indexName: 'ssu_pral_user_fk')
                ->cascadeOnDelete();
            $table->string('previous_status')->nullable();
            $table->string('new_status');
            $table->text('previous_notes')->nullable();
            $table->text('new_notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refund_audit_logs');
    }
};
