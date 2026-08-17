<?php

namespace App\Http\Middleware;

use App\Services\Auth\SingleSessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce one active session per user and explain forced logouts on the login page.
 */
class EnforceSingleSession
{
    public function __construct(
        private SingleSessionService $singleSession,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('account.single_session.enabled', true)) {
            return $next($request);
        }

        $sessionId = $request->session()->getId();

        if (Auth::check()) {
            $user = $request->user();

            if ($user && ! $user->isAccountActive()) {
                Auth::guard('web')->logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()
                    ->route('login')
                    ->with('error', __('auth.account_disabled'));
            }

            if ($user && ! $this->singleSession->isActiveSession($user, $sessionId)) {
                Auth::guard('web')->logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()
                    ->route('login')
                    ->with('error', $this->singleSession->kickedMessage());
            }

            return $next($request);
        }

        if ($this->singleSession->isKickedSession($sessionId)) {
            // Start a clean session so the flash survives on the login page.
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('error', $this->singleSession->kickedMessage());
        }

        return $next($request);
    }
}
