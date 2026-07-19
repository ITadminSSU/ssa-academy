<?php

use App\Support\Branding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        $brandName = Branding::name();
        $brandAuthor = Branding::author();
        $brandKeywords = Branding::keywords();
        $brandDescription = Branding::description();

        DB::table('settings')->orderBy('id')->chunkById(50, function ($settings) use ($brandName, $brandAuthor, $brandKeywords, $brandDescription) {
            foreach ($settings as $setting) {
                $fields = json_decode($setting->fields, true);

                if (!is_array($fields)) {
                    continue;
                }

                $changed = false;

                foreach (['name', 'title', 'mail_from_name'] as $key) {
                    if (isset($fields[$key]) && Branding::isLegacyName($fields[$key])) {
                        $fields[$key] = $brandName;
                        $changed = true;
                    }
                }

                if (isset($fields['author']) && Branding::isLegacyName($fields['author'])) {
                    $fields['author'] = $brandAuthor;
                    $changed = true;
                }

                if ($setting->type === 'system') {
                    if (isset($fields['keywords']) && (Branding::isLegacyName($fields['keywords']) || str_contains(strtolower((string) $fields['keywords']), 'learning management system'))) {
                        $fields['keywords'] = $brandKeywords;
                        $changed = true;
                    }

                    if (isset($fields['description']) && (Branding::isLegacyName($fields['description']) || str_contains(strtolower((string) $fields['description']), 'lms academy'))) {
                        $fields['description'] = $brandDescription;
                        $changed = true;
                    }

                    if (isset($fields['slogan']) && str_contains(strtolower((string) $fields['slogan']), 'internal training for your team')) {
                        $fields['slogan'] = Branding::tagline();
                        $changed = true;
                    }
                }

                if ($changed) {
                    DB::table('settings')->where('id', $setting->id)->update([
                        'fields' => json_encode($fields),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        if (Schema::hasTable('pages')) {
            DB::table('pages')->orderBy('id')->chunkById(50, function ($pages) use ($brandName) {
                foreach ($pages as $page) {
                    $updates = [];

                    foreach (['title', 'description', 'meta_description', 'meta_keywords'] as $column) {
                        $value = $page->{$column};

                        if (!is_string($value) || $value === '') {
                            continue;
                        }

                        $updated = $value;
                        $updated = str_ireplace('Mentor LMS', $brandName, $updated);
                        $updated = str_ireplace('LMS Academy', $brandName, $updated);
                        $updated = preg_replace('/\bMentor\b/', $brandName, $updated) ?? $updated;

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

        if (Schema::hasTable('footer_items')) {
            DB::table('footer_items')->orderBy('id')->chunkById(50, function ($items) use ($brandName, $brandAuthor) {
                foreach ($items as $item) {
                    $updates = [];

                    if (is_string($item->title)) {
                        $title = $item->title;
                        $newTitle = str_ireplace(['UI Lib', 'UiLib', 'Mentor LMS', 'LMS Academy', 'Mentor'], [$brandAuthor, $brandAuthor, $brandName, $brandName, $brandName], $title);

                        if ($newTitle !== $title) {
                            $updates['title'] = $newTitle;
                        }
                    }

                    if (is_string($item->items)) {
                        $footerItems = json_decode($item->items, true);

                        if (is_array($footerItems)) {
                            $encoded = json_encode($footerItems);
                            $encoded = str_ireplace(
                                ['uilib@gmail.com', 'UI Lib', 'UiLib', 'Mentor LMS', 'LMS Academy', 'Mentor'],
                                [config('branding.contact_email'), $brandAuthor, $brandAuthor, $brandName, $brandName, $brandName],
                                $encoded
                            );
                            $decoded = json_decode($encoded, true);

                            if ($decoded !== $footerItems) {
                                $updates['items'] = json_encode($decoded);
                            }
                        }
                    }

                    if ($updates !== []) {
                        DB::table('footer_items')->where('id', $item->id)->update($updates);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Branding migration is intentionally not reversible.
    }
};
