<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'signwell_document_id')) {
                $table->string('signwell_document_id', 64)->nullable()->after('legal_confirmation_email_last_error');
            }
            if (! Schema::hasColumn('users', 'signwell_recipient_id')) {
                $table->string('signwell_recipient_id', 64)->nullable()->after('signwell_document_id');
            }
            if (! Schema::hasColumn('users', 'signwell_signing_url')) {
                $table->text('signwell_signing_url')->nullable()->after('signwell_recipient_id');
            }
            if (! Schema::hasColumn('users', 'signwell_status')) {
                $table->string('signwell_status', 40)->nullable()->after('signwell_signing_url');
            }
            if (! Schema::hasColumn('users', 'signwell_completed_at')) {
                $table->timestamp('signwell_completed_at')->nullable()->after('signwell_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'signwell_document_id',
                'signwell_recipient_id',
                'signwell_signing_url',
                'signwell_status',
                'signwell_completed_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
