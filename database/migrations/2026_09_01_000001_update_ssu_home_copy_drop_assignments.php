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

        if ($hero) {
            $hero->update([
                'description' => $this->rewriteCopy((string) $hero->description),
            ]);
        }

        $pillars = PageSection::query()
            ->where('page_id', $page->id)
            ->where('slug', 'pillars')
            ->first();

        if (! $pillars) {
            return;
        }

        $properties = is_array($pillars->properties) ? $pillars->properties : [];
        $items = $properties['array'] ?? [];

        if (! is_array($items)) {
            return;
        }

        foreach ($items as $index => $item) {
            if (! is_array($item) || ! isset($item['description'])) {
                continue;
            }

            $items[$index]['description'] = $this->rewriteCopy((string) $item['description']);
        }

        $properties['array'] = $items;
        $pillars->update(['properties' => $properties]);
    }

    public function down(): void
    {
        // Homepage copy refresh — no rollback.
    }

    private function rewriteCopy(string $text): string
    {
        return str_replace(
            [
                'video lessons, assignments, quizzes',
                'video lessons, assignments, and quizzes',
                'every lesson, assignment, and quiz',
                'SSU-verified',
                'verified SSU',
            ],
            [
                'video lessons, quizzes',
                'video lessons and quizzes',
                'every lesson and quiz',
                'SSA-verified',
                'verified SSA',
            ],
            $text,
        );
    }
};
