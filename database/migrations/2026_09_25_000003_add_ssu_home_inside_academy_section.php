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
            ->where('slug', 'inside_academy')
            ->exists();

        if ($exists) {
            return;
        }

        $section = collect(SsuHomeSections::getSections())->firstWhere('slug', 'inside_academy');

        if (! is_array($section)) {
            return;
        }

        $pillars = DB::table('page_sections')
            ->where('page_id', $page->id)
            ->where('slug', 'pillars')
            ->first();

        $sort = $pillars ? ((int) $pillars->sort + 1) : 4;

        DB::table('page_sections')
            ->where('page_id', $page->id)
            ->where('sort', '>=', $sort)
            ->increment('sort');

        DB::table('page_sections')->insert([
            'page_id' => $page->id,
            'name' => $section['name'],
            'slug' => $section['slug'],
            'title' => $section['title'] ?? null,
            'sub_title' => $section['sub_title'] ?? null,
            'description' => $section['description'] ?? null,
            'thumbnail' => $section['thumbnail'] ?? null,
            'flags' => json_encode($section['flags'] ?? []),
            'properties' => json_encode($section['properties'] ?? []),
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
            ->where('slug', 'inside_academy')
            ->first();

        if (! $section) {
            return;
        }

        $sort = (int) $section->sort;

        DB::table('page_sections')
            ->where('page_id', $page->id)
            ->where('slug', 'inside_academy')
            ->delete();

        DB::table('page_sections')
            ->where('page_id', $page->id)
            ->where('sort', '>', $sort)
            ->decrement('sort');
    }
};
