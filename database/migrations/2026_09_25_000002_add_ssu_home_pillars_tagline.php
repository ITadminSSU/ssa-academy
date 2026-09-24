<?php

use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private string $tagline = 'We help you build skills that matter in the real world.';

    public function up(): void
    {
        $page = Page::query()->where('slug', 'ssu-home')->first();

        if (! $page) {
            return;
        }

        $section = PageSection::query()
            ->where('page_id', $page->id)
            ->where('slug', 'pillars')
            ->first();

        if (! $section) {
            return;
        }

        $flags = is_array($section->flags) ? $section->flags : [];
        $flags['sub_title'] = true;

        $current = trim((string) $section->sub_title);
        $payload = ['flags' => $flags];

        if ($current === '') {
            $payload['sub_title'] = $this->tagline;
        }

        $section->update($payload);
    }

    public function down(): void
    {
        // Homepage pillars tagline — no rollback.
    }
};
