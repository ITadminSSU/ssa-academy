<?php

use App\Support\DashboardWelcomeOverlay;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_welcome_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('priority')->default(10);
            $table->unsignedInteger('weight')->default(100);
            $table->string('show_frequency', 32)->default('until_dismissed');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('headline')->nullable();
            $table->text('body')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->string('poster_url', 1000)->nullable();
            $table->string('video_type', 16)->default('none');
            $table->string('video_url', 2000)->nullable();
            $table->boolean('autoplay_muted')->default(true);
            $table->timestamps();

            $table->index(['enabled', 'priority']);
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('dashboard_welcome_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained('dashboard_welcome_campaigns')->cascadeOnDelete();
            $table->string('version', 64);
            $table->timestamp('dismissed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'campaign_id']);
        });

        $setting = DB::table('settings')->where('type', 'dashboard_welcome_overlay')->first();
        $fields = DashboardWelcomeOverlay::defaults();

        if ($setting && is_string($setting->fields)) {
            $decoded = json_decode($setting->fields, true);
            if (is_array($decoded)) {
                $fields = array_merge($fields, $decoded);
            }
        }

        $normalized = DashboardWelcomeOverlay::fromFields($fields);

        DB::table('dashboard_welcome_campaigns')->insert([
            'title' => 'Default welcome',
            'enabled' => (bool) ($normalized['enabled'] ?? false),
            'priority' => 10,
            'weight' => 100,
            'show_frequency' => 'until_dismissed',
            'starts_at' => null,
            'ends_at' => null,
            'headline' => $normalized['headline'] ?: null,
            'body' => $normalized['body'] ?: null,
            'cta_label' => $normalized['cta_label'] ?: null,
            'cta_url' => $normalized['cta_url'] ?: null,
            'poster_url' => $normalized['poster_url'] ?: null,
            'video_type' => $normalized['video_type'] ?? 'none',
            'video_url' => $normalized['video_url'] ?: null,
            'autoplay_muted' => (bool) ($normalized['autoplay_muted'] ?? true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_welcome_dismissals');
        Schema::dropIfExists('dashboard_welcome_campaigns');
    }
};
