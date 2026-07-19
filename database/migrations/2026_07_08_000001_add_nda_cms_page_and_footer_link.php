<?php

use App\Models\Page;
use Database\Data\Sections\InnerSections;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pages')) {
            return;
        }

        Page::query()->firstOrCreate(
            ['slug' => 'non-disclosure-agreement'],
            [
                'name' => 'Non-Disclosure Agreement',
                'type' => 'inner_page',
                'title' => 'Non-Disclosure Agreement (NDA)',
                'description' => InnerSections::getNdaDescription(),
                'meta_description' => 'Read the Smart Sourcing Academy Non-Disclosure Agreement governing confidentiality of proprietary training materials.',
                'meta_keywords' => 'nda, non-disclosure agreement, confidentiality, proprietary materials, academy agreement',
                'active' => true,
            ]
        );

        if (!Schema::hasTable('footer_items')) {
            return;
        }

        $footerItem = DB::table('footer_items')->where('slug', 'legal_policies')->first();

        if (!$footerItem) {
            return;
        }

        $items = json_decode($footerItem->items, true) ?: [];
        $hasNda = collect($items)->contains(
            fn (array $item) => ($item['url'] ?? '') === '/non-disclosure-agreement'
        );

        if ($hasNda) {
            return;
        }

        $updated = [];
        $ndaInserted = false;

        foreach ($items as $item) {
            $updated[] = $item;

            if (($item['url'] ?? '') === '/terms-and-conditions') {
                $updated[] = [
                    'title' => 'Non-Disclosure Agreement',
                    'url' => '/non-disclosure-agreement',
                ];
                $ndaInserted = true;
            }
        }

        if (!$ndaInserted) {
            $updated[] = [
                'title' => 'Non-Disclosure Agreement',
                'url' => '/non-disclosure-agreement',
            ];
        }

        DB::table('footer_items')
            ->where('slug', 'legal_policies')
            ->update([
                'items' => json_encode($updated),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('pages')) {
            Page::query()->where('slug', 'non-disclosure-agreement')->delete();
        }

        if (!Schema::hasTable('footer_items')) {
            return;
        }

        $footerItem = DB::table('footer_items')->where('slug', 'legal_policies')->first();

        if (!$footerItem) {
            return;
        }

        $items = collect(json_decode($footerItem->items, true) ?: [])
            ->reject(fn (array $item) => ($item['url'] ?? '') === '/non-disclosure-agreement')
            ->values()
            ->all();

        DB::table('footer_items')
            ->where('slug', 'legal_policies')
            ->update([
                'items' => json_encode($items),
                'updated_at' => now(),
            ]);
    }
};
