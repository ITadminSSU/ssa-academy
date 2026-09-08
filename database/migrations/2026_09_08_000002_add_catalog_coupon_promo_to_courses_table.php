<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('courses')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            if (! Schema::hasColumn('courses', 'catalog_coupon_promo')) {
                $table->boolean('catalog_coupon_promo')->default(false)->after('launch_full_upfront_price');
            }

            if (! Schema::hasColumn('courses', 'catalog_coupon_off_remaining')) {
                $table->decimal('catalog_coupon_off_remaining', 10, 2)->nullable()->after('catalog_coupon_promo');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('courses')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            if (Schema::hasColumn('courses', 'catalog_coupon_off_remaining')) {
                $table->dropColumn('catalog_coupon_off_remaining');
            }

            if (Schema::hasColumn('courses', 'catalog_coupon_promo')) {
                $table->dropColumn('catalog_coupon_promo');
            }
        });
    }
};
