<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_change_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('new_email');
            $table->string('token', 64);
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'token']);
        });

        // Remove legacy plain-text tokens written by the old email-change flow.
        if (Schema::hasTable('password_reset_tokens')) {
            DB::table('password_reset_tokens')
                ->where('token', 'not like', '$2y$%')
                ->delete();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_change_tokens');
    }
};
