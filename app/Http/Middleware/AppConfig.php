<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AppConfig
{
    public function __construct(private SettingsService $settingsService) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $systemSetting = $this->settingsService->getSetting(['type' => 'system']);
        $storageSetting = $this->settingsService->getSetting(['type' => 'storage']);
        $bunnyStreamSetting = $this->settingsService->getSetting(['type' => 'bunny_stream']);

        if ($systemSetting) {
            $system = $systemSetting->fields ?? [];
            config(['app.name' => $system['name'] ?? config('app.name')]);
        }

        $storage = $storageSetting->fields ?? ['storage_driver' => 'local'];
        setStorageConfig($storage);

        $bunnyStream = $bunnyStreamSetting?->fields ?? ['enabled' => false];
        setBunnyStreamConfig($bunnyStream);

        return $next($request);
    }
}
