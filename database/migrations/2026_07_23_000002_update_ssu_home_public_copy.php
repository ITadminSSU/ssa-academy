<?php

use App\Models\Page;
use App\Models\PageSection;
use Database\Data\Sections\SsuHomeSections;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $page = Page::query()->where('slug', 'ssu-home')->first();

        if (! $page) {
            return;
        }

        $seeded = collect(SsuHomeSections::getSections())->keyBy('slug');

        foreach (['hero', 'pillars', 'call_to_action'] as $slug) {
            $section = PageSection::query()
                ->where('page_id', $page->id)
                ->where('slug', $slug)
                ->first();

            $defaults = $seeded->get($slug);

            if (! $section || ! $defaults) {
                continue;
            }

            $section->update([
                'description' => $defaults['description'] ?? $section->description,
                'properties' => $defaults['properties'] ?? $section->properties,
            ]);
        }
    }

    public function down(): void
    {
        // Public copy refresh — no rollback.
    }
};
