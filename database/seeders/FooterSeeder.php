<?php

namespace Database\Seeders;

use App\Models\Footer;
use App\Models\FooterItem;
use Illuminate\Database\Seeder;

class FooterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create footer
        $footer = Footer::create([
            'active' => true,
            'slug' => 'footer_1',
            'title' => 'Footer 1',
        ]);

        // Create footer items
        $footerItems = [
            [
                'type' => 'list',
                'slug' => 'company',
                'title' => 'Company',
                'items' => [
                    ['title' => 'About Us', 'url' => '/about-us'],
                    ['title' => 'Our Team', 'url' => '/our-team'],
                    ['title' => 'Careers', 'url' => '/careers'],
                    ['title' => 'Contact Us', 'url' => '/contact-us'],
                ],
            ],
            [
                'type' => 'list',
                'slug' => 'legal_policies',
                'title' => 'Legal & Policies',
                'items' => [
                    ['title' => 'Cookie Policy', 'url' => '/cookie-policy'],
                    ['title' => 'Terms & Conditions', 'url' => '/terms-and-conditions'],
                    ['title' => 'Non-Disclosure Agreement', 'url' => '/non-disclosure-agreement'],
                    ['title' => 'Privacy Policy', 'url' => '/privacy-policy'],
                    ['title' => 'Refund Policy', 'url' => '/refund-policy'],
                ],
            ],
            [
                'type' => 'list',
                'slug' => 'address',
                'title' => 'Address',
                'items' => [
                    ['title' => 'UTAH, USA'],
                    ['title' => 'Email: training@smartsourcingusa.com'],
                ],
            ],
            [
                'type' => 'social_media',
                'slug' => 'social_media',
                'title' => 'Social Media',
                'items' => [
                    ['title' => 'Facebook', 'url' => 'https://www.facebook.com/share/18opWCAsMm/?mibextid=wwXIfr', 'icon' => 'facebook'],
                    ['title' => 'TikTok', 'url' => 'https://www.tiktok.com/@smartsourcingusa?_r=1&_t=ZS-97LU5272CZB', 'icon' => 'tiktok'],
                    ['title' => 'Instagram', 'url' => 'https://www.instagram.com/smartsourcingusa', 'icon' => 'instagram'],
                    ['title' => 'LinkedIn', 'url' => 'https://www.linkedin.com/company/smartsourcingusa/', 'icon' => 'linkedin'],
                ],
            ],
            // [
            //     'type' => 'payment_methods',
            //     'slug' => 'payment_methods',
            //     'title' => 'We support multiple payment gateways.',
            //     'items' => [
            //         ['image' => '/assets/payment/stripe.png'],
            //         ['image' => '/assets/payment/paypal.png'],
            //         ['image' => '/assets/payment/mollie.png'],
            //         ['image' => '/assets/payment/paystack.png'],
            //     ],
            // ],
            [
                'type' => 'copyright',
                'slug' => 'copyright',
                'title' => '© Copyright 2026 Smart Sourcing USA. All rights reserved.',
                'items' => [],
            ],
        ];

        foreach ($footerItems as $itemData) {
            FooterItem::create([
                'footer_id' => $footer->id,
                ...$itemData,
            ]);
        }
    }
}
