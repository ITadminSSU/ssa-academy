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

        DB::table('pages')
            ->whereIn('slug', ['home-1', 'home-2', 'home-3', 'home-4', 'home-5'])
            ->update(['active' => false, 'updated_at' => now()]);

        $ssuHome = DB::table('pages')->where('slug', 'ssu-home')->first();

        if (!$ssuHome) {
            $pageId = DB::table('pages')->insertGetId([
                'name' => 'SSU Academy Landing',
                'slug' => 'ssu-home',
                'type' => 'collaborative',
                'title' => 'Smart Sourcing Academy',
                'description' => 'Enterprise training for internal teams and external professionals.',
                'meta_description' => 'SSU Academy — structured learning paths with video, assignments, quizzes, and verified certification.',
                'meta_keywords' => 'SSU Academy, Smart Sourcing USA, training, certification, online courses',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            require_once database_path('data/utils/SsuHomeSections.php');

            foreach (\Database\Data\Sections\SsuHomeSections::getSections() as $section) {
                DB::table('page_sections')->insert([
                    'page_id' => $pageId,
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
        } else {
            DB::table('pages')->where('id', $ssuHome->id)->update([
                'active' => true,
                'name' => 'SSU Academy Landing',
                'title' => 'Smart Sourcing Academy',
                'updated_at' => now(),
            ]);
        }

        if (!Schema::hasTable('settings')) {
            return;
        }

        $ssuHomePage = DB::table('pages')->where('slug', 'ssu-home')->first();

        if (!$ssuHomePage) {
            return;
        }

        $homeSetting = DB::table('settings')->where('type', 'home_page')->first();

        $fields = [
            'page_id' => $ssuHomePage->id,
            'page_name' => $ssuHomePage->name,
            'page_slug' => $ssuHomePage->slug,
        ];

        if ($homeSetting) {
            DB::table('settings')->where('id', $homeSetting->id)->update([
                'fields' => json_encode($fields),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('settings')->insert([
                'type' => 'home_page',
                'sub_type' => null,
                'title' => 'Select Home Page',
                'fields' => json_encode($fields),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('pages')) {
            return;
        }

        $legacyHome = DB::table('pages')->where('slug', 'home-1')->first();

        DB::table('pages')->where('slug', 'ssu-home')->update(['active' => false, 'updated_at' => now()]);

        DB::table('pages')
            ->whereIn('slug', ['home-1', 'home-2', 'home-3', 'home-4', 'home-5'])
            ->update(['active' => true, 'updated_at' => now()]);

        if ($legacyHome && Schema::hasTable('settings')) {
            DB::table('settings')->where('type', 'home_page')->update([
                'fields' => json_encode([
                    'page_id' => $legacyHome->id,
                    'page_name' => $legacyHome->name,
                    'page_slug' => $legacyHome->slug,
                ]),
                'updated_at' => now(),
            ]);
        }
    }
};
