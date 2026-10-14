<?php

namespace App\Http\Controllers;

use App\Services\Seo\SitemapBuilder;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function __construct(private SitemapBuilder $sitemap) {}

    public function sitemap(): Response
    {
        return response($this->sitemap->toXml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function robots(): Response
    {
        $sitemap = rtrim((string) config('app.url'), '/').'/sitemap.xml';

        $lines = [
            'User-agent: *',
            'Allow: /',
            '',
            'Disallow: /dashboard',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /forgot-password',
            'Disallow: /verify-email',
            'Disallow: /password-reset',
            'Disallow: /payments',
            'Disallow: /play-course',
            'Disallow: /student',
            'Disallow: /two-factor-challenge',
            'Disallow: /confirm-password',
            'Disallow: /settings',
            'Disallow: /email',
            'Disallow: /messages',
            'Disallow: /legal',
            'Disallow: /confirm-email-change',
            'Disallow: /auth',
            '',
            'Sitemap: '.$sitemap,
            '',
        ];

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
