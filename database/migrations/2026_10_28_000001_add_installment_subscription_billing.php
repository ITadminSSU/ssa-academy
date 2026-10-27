<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->decimal('installment_price', 10, 2)->nullable()->after('subscription_price');
            $table->unsignedSmallInteger('installment_months')->nullable()->after('installment_price');
            $table->string('stripe_ongoing_price_id')->nullable()->after('stripe_price_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedSmallInteger('installment_paid_count')->default(0)->after('stripe_price_id');
            $table->string('open_invoice_id')->nullable()->after('installment_paid_count');
            $table->text('open_invoice_url')->nullable()->after('open_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['installment_paid_count', 'open_invoice_id', 'open_invoice_url']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['installment_price', 'installment_months', 'stripe_ongoing_price_id']);
        });
    }
};
