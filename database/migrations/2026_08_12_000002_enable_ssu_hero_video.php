<?php

use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $page = Page::query()->where('slug', 'ssu-home')->first();

        if (! $page) {
            return;
        }

        $hero = PageSection::query()
            ->where('page_id', $page->id)
            ->where('slug', 'hero')
            ->first();

        if (! $hero) {
            return;
        }

        $flags = array_merge($hero->flags ?? [], [
            'thumbnail' => true,
            'video_url' => true,
        ]);

        $hero->forceFill([
            'flags' => $flags,
            'thumbnail' => $hero->thumbnail ?: '/assets/images/ssu-about/about-hero.png',
        ])->save();
    }

    public function down(): void
    {
        $page = Page::query()->where('slug', 'ssu-home')->first();

        if (! $page) {
            return;
        }

        $hero = PageSection::query()
            ->where('page_id', $page->id)
            ->where('slug', 'hero')
            ->first();

        if (! $hero) {
            return;
        }

        $flags = $hero->flags ?? [];
        unset($flags['thumbnail'], $flags['video_url']);

        $hero->forceFill(['flags' => $flags])->save();
    }
};
