<?php

use App\Models\FooterItem;
use App\Models\Page;
use App\Models\PageSection;
use Database\Data\PageData;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;

return new class extends Migration
{
    public function up(): void
    {
        $this->restoreOurTeamPage();
        $this->pointFooterOurTeamToAbout();
    }

    public function down(): void
    {
        // Keep the restored page and About Us footer link.
    }

    private function restoreOurTeamPage(): void
    {
        $page = Page::query()->where('slug', 'our-team')->first();

        if ($page) {
            $page->update(['active' => true]);

            return;
        }

        $pageData = collect(PageData::getInnerPages())->firstWhere('slug', 'our-team');

        if (! is_array($pageData)) {
            return;
        }

        $page = Page::create(array_merge(Arr::except($pageData, ['sections']), [
            'type' => 'inner_page',
            'active' => true,
            'description' => $pageData['description'] ?? '',
        ]));

        foreach ($pageData['sections'] ?? [] as $section) {
            $section['page_id'] = $page->id;
            PageSection::create($section);
        }
    }

    private function pointFooterOurTeamToAbout(): void
    {
        FooterItem::query()
            ->where('type', 'list')
            ->get()
            ->each(function (FooterItem $item) {
                $links = $item->items;

                if (! is_array($links)) {
                    return;
                }

                $changed = false;

                foreach ($links as $index => $link) {
                    $title = strtolower(trim((string) ($link['title'] ?? '')));
                    $url = (string) ($link['url'] ?? '');

                    if ($title === 'our team' || str_contains($url, '/our-team')) {
                        $links[$index]['url'] = '/about-us';
                        $changed = true;
                    }
                }

                if ($changed) {
                    $item->update(['items' => $links]);
                }
            });
    }
};
