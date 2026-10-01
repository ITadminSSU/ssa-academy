<?php

use App\Models\Page;
use App\Support\LegalPageCopy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        Page::query()
            ->whereIn('slug', LegalPageCopy::SLUGS)
            ->orderBy('id')
            ->each(function (Page $page): void {
                $description = (string) ($page->description ?? '');

                if ($description === '') {
                    return;
                }

                $stripped = LegalPageCopy::stripLeadingPageTitle($description, (string) $page->name);

                if ($stripped === $description) {
                    return;
                }

                $page->description = $stripped;
                $page->save();
            });
    }

    public function down(): void
    {
        // The removed headings only repeated the page name.
    }
};
