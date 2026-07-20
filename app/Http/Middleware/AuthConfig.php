<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthConfig
{
    public function __construct(private SettingsService $settingsService) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authSetting = $this->settingsService->getSetting([
            'type' => 'auth',
            'sub_type' => 'google',
        ]);
        $recaptchaSetting = $this->settingsService->getSetting([
            'type' => 'auth',
            'sub_type' => 'recaptcha',
        ]);

        $auth = $authSetting->fields ?? [];
        $recaptcha = $recaptchaSetting->fields ?? [];

        // Google Auth configuration
        config([
            'services.google.status' => $auth['active'] ?? false,
            'services.google.client_id' => $auth['client_id'] ?? null,
            'services.google.client_secret' => $auth['client_secret'] ?? null,
            'services.google.redirect' => $auth['redirect'] ?? null,
        ]);

        // Recaptcha configuration
        config([
            'captcha.status' => $recaptcha['active'] ?? false,
            'captcha.secret' => $recaptcha['secret_key'] ?? null,
            'captcha.sitekey' => $recaptcha['site_key'] ?? null,
        ]);

        return $next($request);
    }
}
