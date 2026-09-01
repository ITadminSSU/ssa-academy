<?php

use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private string $newSubtitle = 'Join SMARTSOURCING USA ACADEMY Today';

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

        $current = trim((string) $section->sub_title);

        if ($current === $this->newSubtitle) {
            return;
        }

        if ($current === '' || str_contains(strtolower($current), 'ssu')) {
            $section->update([
                'sub_title' => $this->newSubtitle,
            ]);
        }
    }

    public function down(): void
    {
        // Homepage CTA copy refresh — no rollback.
    }
};
