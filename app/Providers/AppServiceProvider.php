<?php

namespace App\Providers;

use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payment\PaymentGatewaySyncService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('system_settings', function (): ?Setting {
            try {
                if (isDBConnected() && Schema::hasTable('settings')) {
                    return Setting::where('type', 'system')->first();
                }

                return null;
            } catch (\Throwable $th) {
                return null;
            }
        });

        $this->app->singleton('intro_page', function (): ?Page {
            try {
                if (! isDBConnected() || ! Schema::hasTable('settings') || ! Schema::hasTable('pages')) {
                    return null;
                }

                $home = Setting::where('type', 'home_page')->first();
                $pageId = $home?->fields['page_id'] ?? null;

                if (! $pageId) {
                    return null;
                }

                return Page::where('id', $pageId)
                    ->with(['sections' => function ($query) {
                        $query->orderBy('sort', 'asc');
                    }])
                    ->first();
            } catch (\Throwable $th) {
                return null;
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Fix for shared hosting missing CURL_SSLVERSION_TLSv1_2 constant
        if (!defined('CURL_SSLVERSION_TLSv1_2')) {
            define('CURL_SSLVERSION_TLSv1_2', 6); // 6 = TLSv1.2
        }

        ResetPassword::createUrlUsing(function (User $user, string $token) {
            return env('FRONTEND_URL') . '/reset-password?token=' . $token . '&email=' . $user->email;
        });

        // Trust proxies when behind ngrok, Docker, or nginx so URLs/schemes resolve correctly.
        if (app()->environment('local') || request()->hasHeader('X-Forwarded-Proto')) {
            request()->setTrustedProxies(
                ['*'],
                \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
                    \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
                    \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
                    \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO |
                    \Illuminate\Http\Request::HEADER_X_FORWARDED_PREFIX
            );
        }

        if (str_starts_with((string) config('app.url'), 'https://')
            || request()->header('X-Forwarded-Proto') === 'https'
            || request()->secure()) {
            URL::forceScheme('https');
        }

        if (app()->environment('local') && !app()->runningInConsole() && request()->getHost()) {
            // Match asset/script URLs to the host the browser actually uses (localhost vs 127.0.0.1).
            URL::forceRootUrl(request()->getSchemeAndHttpHost());
        } elseif (config('app.url')) {
            URL::forceRootUrl(config('app.url'));
        }

        try {
            app(PaymentGatewaySyncService::class)->syncStripeFromEnvironment();
        } catch (\Throwable $th) {
            // Ignore during install or when the database is unavailable.
        }
    }
}
