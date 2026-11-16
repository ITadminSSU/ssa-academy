<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscriptions') || Schema::hasColumn('subscriptions', 'access_retained_at')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('access_retained_at')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('subscriptions') || ! Schema::hasColumn('subscriptions', 'access_retained_at')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('access_retained_at');
        });
    }
};
