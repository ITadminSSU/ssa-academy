<?php

namespace App\Http\Middleware;

use App\Support\Branding;
use App\Support\Features;
use App\Services\AuthService;
use App\Services\LegalAgreementService;
use App\Services\Course\CourseCategoryService;
use App\Services\NotificationService;
use App\Services\SettingsService;
use App\Services\StudentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cookie;
use Modules\Language\Models\Language;
use Modules\Language\Services\LanguageService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\App;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    public function __construct(
        private StudentService $studentService,
        private SettingsService $settingsService,
        private LanguageService $languageService,
        private NotificationService $notificationService,
        private LegalAgreementService $legalAgreement,
        private CourseCategoryService $courseCategoryService,
    ) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        // Avoid blank-page reload loops in local dev after `npm run build`.
        if (app()->environment('local')) {
            return '';
        }

        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $th) {
            return [];
        }

        $user = Auth::user();
        $system = $this->normalizeSystemSettings(app('system_settings'));
        $cartCount = $user ? $this->studentService->getCartCount() : 0;

        if (Schema::hasTable('languages')) {
            $langs = Language::where('is_active', true)->orderBy('is_default', 'desc')->get();
            $defaultLang = $langs->where('is_default', true)->first();
            $default = $defaultLang?->code ?? 'en';
            config(['app.locale' => $default]);
            $locale = Cookie::get('locale', $default);
            App::setLocale($locale);

            $this->languageService->setLanguageProperties($locale);
        } else {
            $langs = [];
            $locale = Cookie::get('locale', 'en');
        }

        return [
            ...parent::share($request),
            'page' => app('intro_page'),
            'auth' => [
                'user' => $user,
                'dashboardUrl' => $user ? app(AuthService::class)->homeUrlFor($user) : null,
                'dashboardRoute' => $user ? app(AuthService::class)->dashboardRouteNameFor($user) : null,
                'legalAgreementRequired' => $user ? $this->legalAgreement->requiresAcceptance($user) : false,
                'legalAgreementUrl' => route('legal.agreement.show'),
            ],
            'system' => $system,
            'branding' => Branding::payload(),
            'features' => Features::payload(),
            'customize' => false,
            'navbar' => Schema::hasTable('navbars') ? $this->filterNavbar($this->settingsService->getNavbar('navbar_1')) : null,
            'footer' => Schema::hasTable('footers') ? $this->settingsService->getFooter('footer_1') : null,
            'notifications' => $user ? $this->notificationService->notifications(['unread' => true]) : [],
            'learnerNav' => $user
                ? [
                    'categories' => $this->courseCategoryService->getLearnerNavCategories($user),
                    'guides' => Schema::hasTable('professional_development_guides')
                        ? \App\Models\ProfessionalDevelopmentGuide::where('is_published', true)
                            ->orderBy('sort')
                            ->get(['id', 'key', 'title'])
                        : [],
                ]
                : null,
            'ziggy' => fn(): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'flash' => [
                'error' => fn() => $request->session()->get('error'),
                'warning' => fn() => $request->session()->get('warning'),
                'success' => fn() => $request->session()->get('success'),
            ],
            'langs' => $langs,
            'locale' => $locale,
            'direction' => 'ltr',
            'cartCount' => $cartCount,
            'translate' => [
                'auth' => trans('auth'),
                'button' => trans('button'),
                'common' => trans('common'),
                'dashboard' => trans('dashboard'),
                'frontend' => trans('frontend'),
                'input' => trans('input'),
                'settings' => trans('settings'),
                'table' => trans('table'),
            ],
        ];
    }

    private function filterNavbar($navbar)
    {
        if (!$navbar) {
            return $navbar;
        }

        $items = $navbar->relationLoaded('navbarItems')
            ? $navbar->navbarItems
            : collect($navbar->navbar_items ?? []);

        $filtered = collect($items)
            ->filter(function ($item) {
                if (!($item->active ?? true)) {
                    return false;
                }

                return !Features::shouldHideNavbarItem($item->title ?? null, $item->value ?? null);
            })
            ->values();

        $navbar->setRelation('navbarItems', $filtered);

        return $navbar;
    }

    private function normalizeSystemSettings($system)
    {
        if (!$system || !is_array($system->fields ?? null)) {
            return $system;
        }

        $fields = $system->fields;

        foreach (['logo_dark', 'logo_light', 'favicon', 'banner', 'hero_image', 'og_image'] as $key) {
            if (!empty($fields[$key])) {
                $fields[$key] = public_asset_url($fields[$key]);
            }
        }

        $fields['name'] = Branding::resolveSiteName($fields['name'] ?? null);
        $fields['title'] = Branding::resolveSiteName($fields['title'] ?? null);
        $fields['author'] = Branding::resolveAuthor($fields['author'] ?? null);
        $fields['logo_dark'] = Branding::resolveLogo($fields['logo_dark'] ?? null, 'dark');
        $fields['logo_light'] = Branding::resolveLogo($fields['logo_light'] ?? null, 'light');
        $fields['favicon'] = Branding::isLegacyLogo($fields['favicon'] ?? null)
            ? (Branding::logo('favicon') ?? Branding::logo('icon'))
            : ($fields['favicon'] ?? Branding::logo('favicon'));

        if (empty($fields['keywords']) || Branding::isLegacyName($fields['keywords'])) {
            $fields['keywords'] = Branding::keywords();
        }

        if (empty($fields['description']) || Branding::isLegacyName($fields['description'])) {
            $fields['description'] = Branding::description();
        }

        $system->fields = $fields;

        return $system;
    }
}
