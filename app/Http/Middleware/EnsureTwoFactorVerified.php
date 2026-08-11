<?php

namespace App\Http\Middleware;

use App\Services\Auth\TwoFactorAuthenticationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorVerified
{
    public function __construct(private TwoFactorAuthenticationService $twoFactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->twoFactor->isEnabled($user)) {
            return $next($request);
        }

        if ($request->session()->get('auth.two_factor_confirmed') === true) {
            return $next($request);
        }

        if ($this->isExempt($request)) {
            return $next($request);
        }

        return redirect()->route('two-factor.challenge');
    }

    private function isExempt(Request $request): bool
    {
        return $request->routeIs([
            'two-factor.challenge',
            'two-factor.challenge.store',
            'logout',
            'legal.agreement.show',
            'legal.agreement.store',
        ]);
    }
}
