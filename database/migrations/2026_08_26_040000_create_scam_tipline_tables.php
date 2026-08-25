<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scam_tipline_reports')) {
            Schema::create('scam_tipline_reports', function (Blueprint $table) {
                $table->id();
                $table->string('reporter_name')->nullable();
                $table->string('reporter_email')->nullable();
                $table->text('link')->nullable();
                $table->text('normalized_link')->nullable();
                // Short hash keeps MySQL index under the 3072-byte key limit.
                $table->string('normalized_link_hash', 64)->nullable()->index();
                $table->text('details')->nullable();
                $table->string('screenshot')->nullable();
                $table->string('screenshot_name')->nullable();
                $table->string('status', 32)->default('new')->index();
                $table->string('public_note', 500)->nullable();
                $table->boolean('is_published')->default(false)->index();
                $table->timestamp('confirmed_at')->nullable()->index();
                $table->foreignId('duplicate_of_id')->nullable()->constrained('scam_tipline_reports')->nullOnDelete();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            $this->repairExistingReportsTable();
        }

        if (! Schema::hasTable('scam_tipline_audits')) {
            Schema::create('scam_tipline_audits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('scam_tipline_report_id')->constrained('scam_tipline_reports')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 64);
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32)->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('scam_tipline_audits');
        Schema::dropIfExists('scam_tipline_reports');
    }

    private function repairExistingReportsTable(): void
    {
        // Drop oversized index from the failed first attempt (if present).
        try {
            Schema::table('scam_tipline_reports', function (Blueprint $table) {
                $table->dropIndex('scam_tipline_reports_normalized_link_index');
            });
        } catch (\Throwable) {
            // Index may not exist.
        }

        Schema::table('scam_tipline_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('scam_tipline_reports', 'normalized_link_hash')) {
                $table->string('normalized_link_hash', 64)->nullable()->after('normalized_link')->index();
            }
        });

        // Widen link columns if they were created as VARCHAR(2048).
        try {
            DB::statement('ALTER TABLE `'.DB::getTablePrefix().'scam_tipline_reports` MODIFY `link` TEXT NULL');
            DB::statement('ALTER TABLE `'.DB::getTablePrefix().'scam_tipline_reports` MODIFY `normalized_link` TEXT NULL');
        } catch (\Throwable) {
            // Already TEXT or unsupported on this engine.
        }
    }
};
