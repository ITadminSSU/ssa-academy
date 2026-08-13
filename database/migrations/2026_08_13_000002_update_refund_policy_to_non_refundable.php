<?php

use Database\Data\Sections\InnerSections;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        DB::table('pages')
            ->where('slug', 'refund-policy')
            ->update([
                'description' => InnerSections::getRefundPolicyDescription(),
                'meta_description' => 'SMARTSOURCING USA ACADEMY payments are non-refundable. Read our Refund Policy for course fees, subscriptions, and paid services.',
                'meta_keywords' => 'refund policy, non-refundable, course fees, subscription billing, SMARTSOURCING USA ACADEMY',
                'updated_at' => now(),
            ]);

        DB::table('pages')
            ->where('slug', 'terms-and-conditions')
            ->update([
                'description' => InnerSections::getTermsAndConditionsDescription(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Content rollback is not applied; restore from Admin → Pages if needed.
    }
};
