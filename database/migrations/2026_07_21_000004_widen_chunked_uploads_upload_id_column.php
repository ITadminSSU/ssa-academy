<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cloudflare R2 multipart upload IDs can exceed VARCHAR(255).
     */
    public function up(): void
    {
        Schema::table('chunked_uploads', function (Blueprint $table) {
            $table->text('upload_id')->nullable()->comment('S3-compatible multipart upload ID')->change();
        });
    }

    public function down(): void
    {
        Schema::table('chunked_uploads', function (Blueprint $table) {
            $table->string('upload_id')->nullable()->comment('AWS S3 multipart upload ID')->change();
        });
    }
};
