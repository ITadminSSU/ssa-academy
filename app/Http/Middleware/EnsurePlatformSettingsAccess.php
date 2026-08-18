<?php

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformSettingsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->canManagePlatformSettings()) {
            return $next($request);
        }

        return redirect(app(AuthService::class)->homeUrlFor($user))
            ->with('error', 'You do not have permission to access platform settings.');
    }
}
