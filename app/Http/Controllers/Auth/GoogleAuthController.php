<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserType;
use App\Models\User;
use App\Services\AuthService;
use App\Services\LegalAgreementService;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private LegalAgreementService $legalAgreement,
    ) {}

    /**
     * Show the google sighup/login form.
     */
    public function show(Request $request)
    {
        session()->put('from', $request->from);

        return Socialite::driver('google')->redirect();
    }

    /**
     * Back to the specific route after login.
     */
    public function callback()
    {
        $from = session()->get('from');

        try {
            $user = Socialite::driver('google')->user();
            $registered = User::where('google_id', $user->id)->orWhere('email', $user->email)->first();

            if ($registered) {
                // Update existing user's tokens
                $this->authService->updateGoogleTokens($registered, $user);
                Auth::login($registered, true);
            } else {
                $registered = $this->authService->googleAuthRegister($user);

                event(new Registered($registered));
                Auth::login($registered, true);
            }

            if ($this->legalAgreement->requiresAcceptance($registered)) {
                return redirect()->route('legal.agreement.show');
            }

            $dashboardUrl = $this->authService->homeUrlFor($registered);

            if ($from && $from == 'api') {
                session()->forget('from');

                return redirect()->intended(match ($registered->role) {
                    UserType::STUDENT->value => config('app.frontend_url') . '/student',
                    default => config('app.frontend_url') . '/dashboard',
                });
            }

            return redirect()->intended($dashboardUrl);
        } catch (\Throwable $th) {
            if ($from && $from == 'api') {
                session()->forget('from');
                return redirect()->intended(config('app.frontend_url') . '/login');
            } else {
                return redirect()->route('login')->with('error', $th->getMessage());
            }
        }
    }
}
