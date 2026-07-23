<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pages') || !Schema::hasTable('page_sections')) {
            return;
        }

        $page = DB::table('pages')->where('slug', 'ssu-home')->first();

        if (!$page) {
            return;
        }

        $existingSections = DB::table('page_sections')->where('page_id', $page->id)->count();

        if ($existingSections > 0) {
            return;
        }

        require_once database_path('data/utils/SsuHomeSections.php');

        foreach (\Database\Data\Sections\SsuHomeSections::getSections() as $section) {
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
                'active' => $section['active'] ?? true,
                'sort' => $section['sort'] ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Sections may have been customized in admin — do not delete on rollback.
    }
};
