<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_conversations')) {
            Schema::table('chat_conversations', function (Blueprint $table) {
                if (! Schema::hasColumn('chat_conversations', 'resolved_at')) {
                    $table->timestamp('resolved_at')->nullable()->after('last_message_at');
                }
                if (! Schema::hasColumn('chat_conversations', 'resolved_by')) {
                    $table->foreignId('resolved_by')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('chat_conversations', 'pinned_message_id')) {
                    $table->unsignedBigInteger('pinned_message_id')->nullable()->after('resolved_by');
                }
            });
        }

        if (Schema::hasTable('chat_participants')) {
            Schema::table('chat_participants', function (Blueprint $table) {
                if (! Schema::hasColumn('chat_participants', 'is_muted')) {
                    $table->boolean('is_muted')->default(false)->after('is_active');
                }
            });
        }

        if (Schema::hasTable('chat_messages')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                if (! Schema::hasColumn('chat_messages', 'attachment_type')) {
                    $table->string('attachment_type', 16)->nullable()->after('attachment_name');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('chat_messages') && Schema::hasColumn('chat_messages', 'attachment_type')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->dropColumn('attachment_type');
            });
        }

        if (Schema::hasTable('chat_participants') && Schema::hasColumn('chat_participants', 'is_muted')) {
            Schema::table('chat_participants', function (Blueprint $table) {
                $table->dropColumn('is_muted');
            });
        }

        if (Schema::hasTable('chat_conversations')) {
            Schema::table('chat_conversations', function (Blueprint $table) {
                if (Schema::hasColumn('chat_conversations', 'resolved_by')) {
                    $table->dropConstrainedForeignId('resolved_by');
                }
                if (Schema::hasColumn('chat_conversations', 'resolved_at')) {
                    $table->dropColumn('resolved_at');
                }
                if (Schema::hasColumn('chat_conversations', 'pinned_message_id')) {
                    $table->dropColumn('pinned_message_id');
                }
            });
        }
    }
};
