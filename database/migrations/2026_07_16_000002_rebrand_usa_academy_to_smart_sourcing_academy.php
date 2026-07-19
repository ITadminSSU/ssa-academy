<?php

use Database\Data\Sections\InnerSections;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $from = 'Smart Sourcing USA Academy';

    private string $to = 'Smart Sourcing Academy';

    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            DB::table('settings')->orderBy('id')->chunkById(50, function ($settings) {
                foreach ($settings as $setting) {
                    $fields = json_decode($setting->fields, true);

                    if (!is_array($fields)) {
                        continue;
                    }

                    $encoded = json_encode($fields);
                    $updated = str_replace($this->from, $this->to, $encoded);

                    if ($updated !== $encoded) {
                        DB::table('settings')->where('id', $setting->id)->update([
                            'fields' => $updated,
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
        }

        if (Schema::hasTable('pages')) {
            $pageSlugs = [
                'contact-us' => InnerSections::getContactUsDescription(),
                'privacy-policy' => InnerSections::getPrivacyPolicyDescription(),
                'refund-policy' => InnerSections::getRefundPolicyDescription(),
            ];

            foreach ($pageSlugs as $slug => $description) {
                DB::table('pages')
                    ->where('slug', $slug)
                    ->update([
                        'description' => $description,
                        'updated_at' => now(),
                    ]);
            }

            DB::table('pages')->orderBy('id')->chunkById(50, function ($pages) {
                foreach ($pages as $page) {
                    $updates = [];

                    foreach (['title', 'description', 'meta_description', 'meta_keywords'] as $column) {
                        $value = $page->{$column};

                        if (!is_string($value) || $value === '') {
                            continue;
                        }

                        $updated = str_replace($this->from, $this->to, $value);

                        if ($updated !== $value) {
                            $updates[$column] = $updated;
                        }
                    }

                    if ($updates !== []) {
                        $updates['updated_at'] = now();
                        DB::table('pages')->where('id', $page->id)->update($updates);
                    }
                }
            });
        }

        if (Schema::hasTable('home_pages')) {
            DB::table('home_pages')->orderBy('id')->chunkById(50, function ($homePages) {
                foreach ($homePages as $homePage) {
                    $updates = [];

                    foreach (['title', 'description', 'meta_description', 'meta_keywords'] as $column) {
                        $value = $homePage->{$column};

                        if (!is_string($value) || $value === '') {
                            continue;
                        }

                        $updated = str_replace($this->from, $this->to, $value);

                        if ($updated !== $value) {
                            $updates[$column] = $updated;
                        }
                    }

                    if ($updates !== []) {
                        $updates['updated_at'] = now();
                        DB::table('home_pages')->where('id', $homePage->id)->update($updates);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Content rollback is not applied to preserve admin edits.
    }
};
