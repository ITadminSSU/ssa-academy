<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use App\Support\MailConfigurator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SmtpConfig
{
    public function __construct(private SettingsService $settingsService) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $smtp = $this->settingsService->getSetting(['type' => 'smtp']);

        if ($smtp) {
            MailConfigurator::applyFromSetting($smtp);
        }

        return $next($request);
    }
}
