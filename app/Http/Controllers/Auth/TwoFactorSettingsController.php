<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\TwoFactorAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorSettingsController extends Controller
{
    public function __construct(private TwoFactorAuthenticationService $twoFactor) {}

    public function start(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $this->twoFactor->canUse($user)) {
            abort(403);
        }

        $setup = $this->twoFactor->beginSetup($user);

        return back()->with([
            'two_factor_setup' => $setup,
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $this->twoFactor->canUse($user)) {
            abort(403);
        }

        $request->validate([
            'code' => 'required|string',
        ]);

        try {
            $recoveryCodes = $this->twoFactor->confirmSetup($user, $request->input('code'));
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'code' => $exception->getMessage(),
            ]);
        }

        $request->session()->put('auth.two_factor_confirmed', true);

        return back()->with([
            'two_factor_recovery_codes' => $recoveryCodes,
            'success' => 'Two-factor authentication is enabled. Save your recovery codes.',
        ]);
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $this->twoFactor->canUse($user)) {
            abort(403);
        }

        $request->validate([
            'password' => 'required|string',
            'code' => 'required|string',
        ]);

        if (! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'The password is incorrect.',
            ]);
        }

        if (! $this->twoFactor->verifyLoginCode($user, $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => 'The authentication code is invalid.',
            ]);
        }

        $this->twoFactor->disable($user);
        $request->session()->forget('auth.two_factor_confirmed');

        return back()->with('success', 'Two-factor authentication has been disabled.');
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $this->twoFactor->isEnabled($user)) {
            abort(403);
        }

        $request->validate([
            'password' => 'required|string',
            'code' => 'required|string',
        ]);

        if (! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'The password is incorrect.',
            ]);
        }

        if (! $this->twoFactor->verifyLoginCode($user, $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => 'The authentication code is invalid.',
            ]);
        }

        $codes = $this->twoFactor->regenerateRecoveryCodes($user);

        return back()->with([
            'two_factor_recovery_codes' => $codes,
            'success' => 'New recovery codes generated. Save them now.',
        ]);
    }
}
