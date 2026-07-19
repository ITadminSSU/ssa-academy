<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('footer_items')) {
            return;
        }

        DB::table('footer_items')
            ->where('slug', 'address')
            ->update([
                'items' => json_encode([
                    ['title' => 'UTAH, USA'],
                    ['title' => 'Email: training@smartsourcingusa.com'],
                ]),
                'updated_at' => now(),
            ]);

        DB::table('footer_items')
            ->where('slug', 'social_media')
            ->update([
                'items' => json_encode([
                    [
                        'title' => 'Facebook',
                        'url' => 'https://www.facebook.com/share/18opWCAsMm/?mibextid=wwXIfr',
                        'icon' => 'facebook',
                    ],
                    [
                        'title' => 'TikTok',
                        'url' => 'https://www.tiktok.com/@smartsourcingusa?_r=1&_t=ZS-97LU5272CZB',
                        'icon' => 'tiktok',
                    ],
                    [
                        'title' => 'Instagram',
                        'url' => 'https://www.instagram.com/smartsourcingusa',
                        'icon' => 'instagram',
                    ],
                    [
                        'title' => 'LinkedIn',
                        'url' => 'https://www.linkedin.com/company/smartsourcingusa/',
                        'icon' => 'linkedin',
                    ],
                ]),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('footer_items')) {
            return;
        }

        DB::table('footer_items')
            ->where('slug', 'address')
            ->update([
                'items' => json_encode([
                    ['title' => 'Corner view Subudbazar, Sylhet, Bangladesh.'],
                    ['title' => 'Email: info@smartsourcingusa.com'],
                    ['title' => 'Phone: +880 1123 456 780'],
                ]),
                'updated_at' => now(),
            ]);

        DB::table('footer_items')
            ->where('slug', 'social_media')
            ->update([
                'items' => json_encode([
                    ['title' => 'Facebook', 'url' => 'https://www.facebook.com/', 'icon' => 'facebook'],
                    ['title' => 'Twitter', 'url' => 'https://www.twitter.com/', 'icon' => 'twitter'],
                    ['title' => 'Instagram', 'url' => 'https://www.instagram.com/', 'icon' => 'instagram'],
                    ['title' => 'LinkedIn', 'url' => 'https://www.linkedin.com/', 'icon' => 'linkedin'],
                ]),
                'updated_at' => now(),
            ]);
    }
};
