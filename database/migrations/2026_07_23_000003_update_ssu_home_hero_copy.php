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

        PageSection::query()
            ->where('page_id', $page->id)
            ->where('slug', 'hero')
            ->update([
                'sub_title' => 'Upskill. Certify your skills. Scale with confidence.',
                'description' => 'Structured learning paths for professionals — video lessons, assignments, quizzes, and verified SSU certificates.',
            ]);
    }

    public function down(): void
    {
        // Copy refresh — no rollback.
    }
};
