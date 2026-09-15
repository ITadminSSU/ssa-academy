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

        DB::table('footer_items')
            ->orderBy('id')
            ->chunkById(50, function ($rows) {
                foreach ($rows as $row) {
                    $items = json_decode($row->items, true);

                    if (! is_array($items) || $items === []) {
                        continue;
                    }

                    $filtered = array_values(array_filter(
                        $items,
                        function ($item) {
                            if (! is_array($item)) {
                                return true;
                            }

                            $url = strtolower(trim((string) ($item['url'] ?? '')));
                            $title = strtolower(trim((string) ($item['title'] ?? '')));

                            if ($url === '/non-disclosure-agreement' || str_ends_with($url, '/non-disclosure-agreement')) {
                                return false;
                            }

                            return $title !== 'non-disclosure agreement';
                        }
                    ));

                    if (count($filtered) === count($items)) {
                        continue;
                    }

                    DB::table('footer_items')->where('id', $row->id)->update([
                        'items' => json_encode($filtered),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('footer_items')) {
            return;
        }

        $footerItem = DB::table('footer_items')->where('slug', 'legal_policies')->first();

        if (! $footerItem) {
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
        $inserted = false;

        foreach ($items as $item) {
            $updated[] = $item;

            if (($item['url'] ?? '') === '/terms-and-conditions') {
                $updated[] = [
                    'title' => 'Non-Disclosure Agreement',
                    'url' => '/non-disclosure-agreement',
                ];
                $inserted = true;
            }
        }

        if (! $inserted) {
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
};
