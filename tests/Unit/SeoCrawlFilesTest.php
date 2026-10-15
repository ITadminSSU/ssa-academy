<?php

use App\Models\Page;
use App\Support\SeoMeta;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Blog\Models\BlogCategory;

beforeEach(function () {
    config(['app.url' => 'https://academy.test']);
    $this->withoutMiddleware();
});

test('robots txt allows public pages and points at the sitemap', function () {
    $response = $this->get('/robots.txt');

    $response->assertOk();
    $response->assertHeader('content-type', 'text/plain; charset=UTF-8');
    $response->assertSee('Allow: /', false);
    $response->assertSee('Disallow: /dashboard', false);
    $response->assertSee('Disallow: /login', false);
    $response->assertSee('Disallow: /register', false);
    $response->assertSee('Disallow: /verify-email', false);
    $response->assertSee('Disallow: /payments', false);
    $response->assertSee('Disallow: /play-course', false);
    $response->assertSee('Sitemap: https://academy.test/sitemap.xml', false);
});

test('sitemap lists public pages and leaves out private or unpublished ones', function () {
    createSeoTables();

    Page::query()->create([
        'name' => 'Drywall',
        'slug' => 'drywall-estimating',
        'type' => 'inner_page',
        'title' => 'Drywall Estimating',
        'active' => true,
    ]);
    Page::query()->create([
        'name' => 'Hidden',
        'slug' => 'hidden-draft',
        'type' => 'inner_page',
        'title' => 'Hidden',
        'active' => false,
    ]);

    BlogCategory::query()->create([
        'name' => 'Field notes',
        'slug' => 'field-notes',
        'status' => 'active',
    ]);
    DB::table('blogs')->insert([
        ['id' => 4, 'status' => 'published', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 9, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::table('instructors')->insert([
        'id' => 3,
        'status' => 'approved',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('instructors')->insert([
        'id' => 8,
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('courses')->insert([
        [
            'id' => 12,
            'slug' => 'drywall-estimating',
            'status' => 'approved',
            'audience' => 'public',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => 13,
            'slug' => 'internal-only',
            'status' => 'approved',
            'audience' => 'internal',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => 14,
            'slug' => 'draft-course',
            'status' => 'draft',
            'audience' => 'public',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::table('exams')->insert([
        [
            'id' => 6,
            'slug' => 'published-exam',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => 7,
            'slug' => 'draft-exam',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $response->assertHeader('content-type', 'application/xml; charset=UTF-8');

    expect($response->getContent())
        ->toContain('https://academy.test/')
        ->toContain('https://academy.test/about-us')
        ->toContain('https://academy.test/faqs')
        ->toContain('https://academy.test/courses/all')
        ->toContain('https://academy.test/blogs/all')
        ->toContain('https://academy.test/blogs/field-notes')
        ->toContain('https://academy.test/drywall-estimating')
        ->toContain('https://academy.test/courses/details/drywall-estimating/12')
        ->toContain('https://academy.test/read/blogs/4')
        ->toContain('https://academy.test/exams/details/published-exam/6')
        ->toContain('https://academy.test/instructors/3')
        ->not->toContain('hidden-draft')
        ->not->toContain('internal-only')
        ->not->toContain('draft-course')
        ->not->toContain('draft-exam')
        ->not->toContain('/read/blogs/9')
        ->not->toContain('/instructors/8')
        ->not->toContain('https://academy.test/login')
        ->not->toContain('https://academy.test/dashboard');
});

test('a missing sitemap section still returns the static urls', function () {
    createSeoTables();
    Schema::drop('blogs');

    DB::table('courses')->insert([
        'id' => 12,
        'slug' => 'drywall-estimating',
        'status' => 'approved',
        'audience' => 'public',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('courses')->insert([
        'id' => 15,
        'slug' => '',
        'status' => 'approved',
        'audience' => 'public',
        'created_at' => now(),
        'updated_at' => 'not-a-date',
    ]);

    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $response->assertHeader('content-type', 'application/xml; charset=UTF-8');

    expect($response->getContent())
        ->toContain('https://academy.test/')
        ->toContain('https://academy.test/about-us')
        ->toContain('https://academy.test/courses/all')
        ->toContain('https://academy.test/courses/details/drywall-estimating/12')
        ->not->toContain('https://academy.test/read/blogs/')
        ->not->toContain('courses/details//15')
        ->not->toContain('https://academy.test/login');
});

test('canonical urls and robots tags follow the public and private paths', function () {
    $faqs = Request::create('/faqs', 'GET');
    $faqs->setRouteResolver(fn () => Route::getRoutes()->match($faqs));

    expect(SeoMeta::canonicalUrl($faqs))->toBe('https://academy.test/faqs')
        ->and(SeoMeta::robotsContent($faqs))->toBe('index, follow')
        ->and(SeoMeta::descriptionOverride($faqs))->toBe(SeoMeta::FAQ_DESCRIPTION);

    $about = Request::create('/about-us', 'GET');
    $about->setRouteResolver(fn () => Route::getRoutes()->match($about));
    expect(SeoMeta::descriptionOverride($about))->toBe(SeoMeta::ABOUT_DESCRIPTION);

    $home = Request::create('/', 'GET');
    $home->setRouteResolver(fn () => Route::getRoutes()->match($home));
    expect(SeoMeta::canonicalUrl($home))->toBe('https://academy.test/')
        ->and(SeoMeta::descriptionOverride($home))->toBe(SeoMeta::HOME_DESCRIPTION)
        ->and(SeoMeta::robotsContent($home))->toBe('index, follow');

    expect(SeoMeta::robotsContent(Request::create('/login', 'GET')))->toBe('noindex, nofollow')
        ->and(SeoMeta::robotsContent(Request::create('/play-course/1/2/3', 'GET')))->toBe('noindex, nofollow')
        ->and(SeoMeta::robotsContent(Request::create('/payments/web/course/1', 'GET')))->toBe('noindex, nofollow')
        ->and(SeoMeta::robotsContent(Request::create('/dashboard', 'GET')))->toBe('noindex, nofollow')
        ->and(SeoMeta::canonicalUrl(Request::create('/courses/details/drywall-estimating/12', 'GET')))
        ->toBe('https://academy.test/courses/details/drywall-estimating/12');
});

function createSeoTables(): void
{
    Schema::dropIfExists('pages');
    Schema::dropIfExists('blogs');
    Schema::dropIfExists('blog_categories');
    Schema::dropIfExists('courses');
    Schema::dropIfExists('exams');
    Schema::dropIfExists('instructors');

    Schema::create('pages', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('slug');
        $table->string('type')->default('inner_page');
        $table->string('title');
        $table->boolean('active')->default(true);
        $table->timestamps();
    });

    Schema::create('blog_categories', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('slug');
        $table->string('status')->default('active');
        $table->integer('sort')->default(0);
        $table->timestamps();
    });

    Schema::create('blogs', function (Blueprint $table) {
        $table->id();
        $table->string('status')->default('draft');
        $table->timestamps();
    });

    Schema::create('instructors', function (Blueprint $table) {
        $table->id();
        $table->string('status')->default('pending');
        $table->timestamps();
    });

    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('slug');
        $table->string('status');
        $table->string('audience')->default('public');
        $table->timestamps();
    });

    Schema::create('exams', function (Blueprint $table) {
        $table->id();
        $table->string('slug');
        $table->string('status')->default('draft');
        $table->timestamps();
    });
}
