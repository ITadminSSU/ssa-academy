<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\SingleSessionService;
use App\Services\Auth\TwoFactorAuthenticationService;
use App\Services\AuthService;
use App\Services\LegalAgreementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private TwoFactorAuthenticationService $twoFactor,
        private AuthService $authService,
        private LegalAgreementService $legalAgreement,
        private SingleSessionService $singleSession,
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $this->twoFactor->isEnabled($user)) {
            return redirect($this->authService->homeUrlFor($user));
        }

        if ($request->session()->get('auth.two_factor_confirmed') === true) {
            return redirect($this->authService->homeUrlFor($user));
        }

        return Inertia::render('auth/two-factor-challenge', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $this->twoFactor->isEnabled($user)) {
            return redirect()->route('login');
        }

        $request->validate([
            'code' => 'required|string',
        ]);

        $key = 'two-factor:'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'code' => "Too many attempts. Try again in {$seconds} seconds.",
            ]);
        }

        if (! $this->twoFactor->verifyLoginCode($user, $request->input('code'))) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'code' => 'The authentication code is invalid.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->put('auth.two_factor_confirmed', true);
        $remember = $request->session()->pull('auth.remember', false);
        $this->singleSession->claim($user, (bool) $remember);

        return redirect()->intended($this->authService->continueUrlAfterAuth($user));
    }
}
