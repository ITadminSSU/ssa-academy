<?php

use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private string $newTitle = 'Why Smart Sourcing Academy';

    private array $legacyTitles = [
        'Why SSU Academy',
        'Why SMART SOURCING ACADEMY',
        'Why Smart Sourcing USA Academy',
    ];

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

        $currentTitle = trim((string) $section->title);

        if ($currentTitle === $this->newTitle) {
            return;
        }

        if ($currentTitle !== '' && ! in_array($currentTitle, $this->legacyTitles, true)) {
            return;
        }

        $section->update([
            'title' => $this->newTitle,
        ]);
    }

    public function down(): void
    {
        // Section title copy update — no rollback.
    }
};
