<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('footer_items')) {
            return;
        }

        $footerItem = DB::table('footer_items')->where('slug', 'company')->first();

        if (! $footerItem) {
            return;
        }

        $items = json_decode($footerItem->items, true) ?: [];
        $hasConnect = collect($items)->contains(
            fn (array $item) => ($item['url'] ?? '') === '/connect-with-us'
        );

        if ($hasConnect) {
            return;
        }

        $updated = [];
        $inserted = false;

        foreach ($items as $item) {
            $updated[] = $item;

            if (($item['url'] ?? '') === '/faqs') {
                $updated[] = [
                    'title' => 'Connect with us',
                    'url' => '/connect-with-us',
                ];
                $inserted = true;
            }
        }

        if (! $inserted) {
            $updated[] = [
                'title' => 'Connect with us',
                'url' => '/connect-with-us',
            ];
        }

        DB::table('footer_items')
            ->where('slug', 'company')
            ->update([
                'items' => json_encode($updated),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('footer_items')) {
            return;
        }

        $footerItem = DB::table('footer_items')->where('slug', 'company')->first();

        if (! $footerItem) {
            return;
        }

        $items = collect(json_decode($footerItem->items, true) ?: [])
            ->reject(fn (array $item) => ($item['url'] ?? '') === '/connect-with-us')
            ->values()
            ->all();

        DB::table('footer_items')
            ->where('slug', 'company')
            ->update([
                'items' => json_encode($items),
                'updated_at' => now(),
            ]);
    }
};
