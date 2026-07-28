<?php

use Database\Data\Sections\InnerSections;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, array{from: string, to: string}> */
    private array $replacements = [
        ['from' => 'Smart Sourcing Academy', 'to' => 'SMARTSOURCING USA ACADEMY'],
        ['from' => 'SMART SOURCING ACADEMY', 'to' => 'SMARTSOURCING USA ACADEMY'],
        ['from' => 'Smart Sourcing USA Academy', 'to' => 'SMARTSOURCING USA ACADEMY'],
    ];

    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            $this->replaceInTable('settings', ['fields']);
        }

        if (Schema::hasTable('pages')) {
            $pageSlugs = [
                'contact-us' => InnerSections::getContactUsDescription(),
                'privacy-policy' => InnerSections::getPrivacyPolicyDescription(),
                'refund-policy' => InnerSections::getRefundPolicyDescription(),
                'terms-and-conditions' => InnerSections::getTermsAndConditionsDescription(),
                'non-disclosure-agreement' => InnerSections::getNdaDescription(),
            ];

            foreach ($pageSlugs as $slug => $description) {
                DB::table('pages')
                    ->where('slug', $slug)
                    ->update([
                        'description' => $description,
                        'updated_at' => now(),
                    ]);
            }

            $this->replaceInTable('pages', ['title', 'description', 'meta_description', 'meta_keywords']);
        }

        if (Schema::hasTable('home_pages')) {
            $this->replaceInTable('home_pages', ['title', 'description', 'meta_description', 'meta_keywords']);
        }

        if (Schema::hasTable('page_sections')) {
            $this->replaceInTable('page_sections', ['title', 'sub_title', 'description', 'properties', 'flags']);
        }
    }

    public function down(): void
    {
        // Content rollback is not applied to preserve admin edits.
    }

    private function replaceInTable(string $table, array $columns): void
    {
        DB::table($table)->orderBy('id')->chunkById(50, function ($rows) use ($table, $columns) {
            foreach ($rows as $row) {
                $updates = [];

                foreach ($columns as $column) {
                    $value = $row->{$column} ?? null;

                    if (!is_string($value) || $value === '') {
                        continue;
                    }

                    $updated = $this->applyReplacements($value);

                    if ($updated !== $value) {
                        $updates[$column] = $updated;
                    }
                }

                if ($updates !== []) {
                    $updates['updated_at'] = now();
                    DB::table($table)->where('id', $row->id)->update($updates);
                }
            }
        });
    }

    private function applyReplacements(string $value): string
    {
        foreach ($this->replacements as $replacement) {
            $value = str_replace($replacement['from'], $replacement['to'], $value);
        }

        return $value;
    }
};
