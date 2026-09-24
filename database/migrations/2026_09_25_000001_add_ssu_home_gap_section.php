<?php

use Database\Data\Sections\SsuHomeSections;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pages') || ! Schema::hasTable('page_sections')) {
            return;
        }

        $page = DB::table('pages')->where('slug', 'ssu-home')->first();

        if (! $page) {
            return;
        }

        $exists = DB::table('page_sections')
            ->where('page_id', $page->id)
            ->where('slug', 'gap')
            ->exists();

        if ($exists) {
            return;
        }

        $gap = collect(SsuHomeSections::getSections())->firstWhere('slug', 'gap');

        if (! is_array($gap)) {
            return;
        }

        $hero = DB::table('page_sections')
            ->where('page_id', $page->id)
            ->where('slug', 'hero')
            ->first();

        $sort = $hero ? ((int) $hero->sort + 1) : 2;

        DB::table('page_sections')
            ->where('page_id', $page->id)
            ->where('sort', '>=', $sort)
            ->increment('sort');

        DB::table('page_sections')->insert([
            'page_id' => $page->id,
            'name' => $gap['name'],
            'slug' => $gap['slug'],
            'title' => $gap['title'] ?? null,
            'sub_title' => $gap['sub_title'] ?? null,
            'description' => $gap['description'] ?? null,
            'thumbnail' => $gap['thumbnail'] ?? null,
            'flags' => json_encode($gap['flags'] ?? []),
            'properties' => json_encode($gap['properties'] ?? []),
            'active' => true,
            'sort' => $sort,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('pages') || ! Schema::hasTable('page_sections')) {
            return;
        }

        $page = DB::table('pages')->where('slug', 'ssu-home')->first();

        if (! $page) {
            return;
        }

        $section = DB::table('page_sections')
            ->where('page_id', $page->id)
            ->where('slug', 'gap')
            ->first();

        if (! $section) {
            return;
        }

        $sort = (int) $section->sort;

        DB::table('page_sections')
            ->where('page_id', $page->id)
            ->where('slug', 'gap')
            ->delete();

        DB::table('page_sections')
            ->where('page_id', $page->id)
            ->where('sort', '>', $sort)
            ->decrement('sort');
    }
};
