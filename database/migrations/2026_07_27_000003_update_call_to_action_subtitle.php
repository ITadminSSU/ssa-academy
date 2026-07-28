<?php

use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private string $newSubtitle = 'Join SMARTSOURCING USA ACADEMY Today';

    private array $legacySubtitles = [
        'Join SSU Academy Today',
        'Join SSU Academy today',
        'JOIN SSU ACADEMY TODAY',
    ];

    public function up(): void
    {
        $page = Page::query()->where('slug', 'ssu-home')->first();

        if (! $page) {
            return;
        }

        $section = PageSection::query()
            ->where('page_id', $page->id)
            ->where('slug', 'call_to_action')
            ->first();

        if (! $section) {
            return;
        }

        $currentSubtitle = trim((string) $section->sub_title);

        if ($currentSubtitle === $this->newSubtitle) {
            return;
        }

        if ($currentSubtitle !== '' && ! in_array($currentSubtitle, $this->legacySubtitles, true)
            && ! str_contains(strtolower($currentSubtitle), 'join ssu academy')) {
            return;
        }

        $section->update([
            'sub_title' => $this->newSubtitle,
        ]);
    }

    public function down(): void
    {
        // Section subtitle copy update — no rollback.
    }
};
