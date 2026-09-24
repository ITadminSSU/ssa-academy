<?php

use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private string $heading = 'START LEARNING TODAY';

    private string $tagline = 'Explore assigned and open-enrollment courses curated for SMARTSOURCING USA teams and partners.';

    public function up(): void
    {
        $page = Page::query()->where('slug', 'ssu-home')->first();

        if (! $page) {
            return;
        }

        $section = PageSection::query()
            ->where('page_id', $page->id)
            ->where('slug', 'top_courses')
            ->first();

        if (! $section) {
            return;
        }

        $flags = is_array($section->flags) ? $section->flags : [];
        $flags['title'] = true;
        $flags['description'] = true;
        $flags['sub_title'] = false;

        $section->update([
            'title' => $this->heading,
            'description' => $this->tagline,
            'flags' => $flags,
        ]);
    }

    public function down(): void
    {
        // Homepage featured-courses header refresh — no rollback.
    }
};
