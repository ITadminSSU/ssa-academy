<?php

namespace App\Http\Middleware;

use App\Support\Branding;
use App\Support\Features;
use App\Services\Auth\TwoFactorAuthenticationService;
use App\Services\AuthService;
use App\Services\LegalAgreementService;
use App\Services\Chat\ChatService;
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
use Inertia\Support\Header;
use Closure;
use Symfony\Component\HttpFoundation\Response;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    public function __construct(
        private StudentService $studentService,
        private SettingsService $settingsService,
        private LanguageService $languageService,
        private NotificationService $notificationService,
        private CourseCategoryService $courseCategoryService,
        private LegalAgreementService $legalAgreement,
        private TwoFactorAuthenticationService $twoFactor,
        private ChatService $chatService,
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
     * Prevent CDNs/proxies from caching full HTML documents and replaying them
     * to Inertia XHR visits (shows as a DOCTYPE string / "plain JSON" modal).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);

        $response->headers->set('Cache-Control', 'private, no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        $vary = array_filter(array_map('trim', explode(',', (string) $response->headers->get('Vary', ''))));
        foreach ([Header::INERTIA, 'Accept', 'Cookie'] as $token) {
            if (! in_array($token, $vary, true)) {
                $vary[] = $token;
            }
        }
        $response->headers->set('Vary', implode(', ', $vary));

        return $response;
    }

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
                'twoFactorEnabled' => $user ? $this->twoFactor->isEnabled($user) : false,
                'canManageTwoFactor' => $user ? $this->twoFactor->canUse($user) : false,
                'canManagePlatformSettings' => $user ? $user->canManagePlatformSettings() : false,
                'messagesUnreadCount' => $user && in_array($user->role, ['student', 'instructor', 'admin'], true)
                    ? $this->chatService->unreadCount($user)
                    : 0,
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
                'info' => fn() => $request->session()->get('info'),
                'success' => fn() => $request->session()->get('success'),
                'status' => fn() => $request->session()->get('status'),
                'two_factor_setup' => fn() => $request->session()->get('two_factor_setup'),
                'two_factor_recovery_codes' => fn() => $request->session()->get('two_factor_recovery_codes'),
            ],
            'langs' => $langs,
            'locale' => $locale,
            'direction' => 'ltr',
            'cartCount' => $cartCount,
            'appTimezone' => config('app.timezone'),
            'bunnyStream' => fn(): array => $this->bunnyStreamPayload(),
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
            'reverb' => $this->reverbPayload(),
        ];
    }

    /**
     * @return array{enabled: bool, key: string, host: string, port: int, scheme: string, authEndpoint: string}
     */
    private function reverbPayload(): array
    {
        $connection = (string) config('broadcasting.default');
        $enabled = $connection === 'reverb' && filled(config('broadcasting.connections.reverb.key'));

        return [
            'enabled' => $enabled,
            'key' => $enabled ? (string) config('broadcasting.connections.reverb.key') : '',
            'host' => $enabled ? (string) env('REVERB_HOST', 'localhost') : '',
            'port' => $enabled ? (int) env('REVERB_PORT', 443) : 443,
            'scheme' => $enabled ? (string) env('REVERB_SCHEME', 'https') : 'https',
            'authEndpoint' => url('/broadcasting/auth'),
        ];
    }

    private function filterNavbar($navbar)
    {
        if (!$navbar) {
            return $navbar;
        }

        $items = $navbar->relationLoaded('navbarItems')
            ? $navbar->navbarItems
            : collect(is_array($navbar->navbar_items ?? null) ? $navbar->navbar_items : []);

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
        $fields['favicon'] = Branding::versionPublicPath('/favicon.ico');

        if (empty($fields['keywords']) || Branding::isLegacyName($fields['keywords'])) {
            $fields['keywords'] = Branding::keywords();
        }

        if (empty($fields['description']) || Branding::isLegacyName($fields['description'])) {
            $fields['description'] = Branding::description();
        }

        $system->fields = $fields;

        return $system;
    }

    /**
     * @return array{enabled: bool, library_id: string, cdn_hostname: string}
     */
    private function bunnyStreamPayload(): array
    {
        if (!Schema::hasTable('settings')) {
            return ['enabled' => false, 'library_id' => '', 'cdn_hostname' => ''];
        }

        $bunnySetting = $this->settingsService->getSetting(['type' => 'bunny_stream']);
        $fields = $bunnySetting?->fields ?? [];

        setBunnyStreamConfig($fields);

        $bunny = app(\App\Services\BunnyStreamService::class);

        return [
            'enabled' => $bunny->isEnabled(),
            'library_id' => $bunny->isEnabled() ? $bunny->libraryId() : '',
            'cdn_hostname' => $bunny->isEnabled() ? $bunny->cdnHostname() : '',
        ];
    }
}
