<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('billing_model')->default('one_time')->after('pricing_type');
            $table->decimal('subscription_price', 10, 2)->nullable()->after('discount_price');
            $table->string('stripe_product_id')->nullable()->after('subscription_price');
            $table->string('stripe_price_id')->nullable()->after('stripe_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'billing_model',
                'subscription_price',
                'stripe_product_id',
                'stripe_price_id',
            ]);
        });
    }
};
