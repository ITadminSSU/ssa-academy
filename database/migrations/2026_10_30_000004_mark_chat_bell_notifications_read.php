<?php

use App\Notifications\ChatMessageNotification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        DB::table('notifications')
            ->where('type', ChatMessageNotification::class)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function down(): void
    {
        // Leave cleared chat bell rows read. Message unread state stays on the sidebar.
    }
};
