<?php

use App\Models\Page;
use Database\Data\Sections\InnerSections;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pages')) {
            return;
        }

        Page::query()
            ->where('slug', 'terms-and-conditions')
            ->update([
                'description' => InnerSections::getTermsAndConditionsDescription(),
                'meta_description' => 'Read Smart Sourcing Academy Terms and Conditions to understand your rights and responsibilities while using our platform.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Content rollback is not applied to preserve admin edits.
    }
};
