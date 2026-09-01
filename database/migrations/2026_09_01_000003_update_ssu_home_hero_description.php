<?php

use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private string $newDescription = 'Structured learning paths for professionals with video lessons, practical assessments, U.S. industry experience, and verified SSA certificates.';

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

        $hero->update([
            'description' => $this->newDescription,
        ]);
    }

    public function down(): void
    {
        // Homepage hero copy refresh — no rollback.
    }
};
