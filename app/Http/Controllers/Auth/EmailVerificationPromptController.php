<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\EmailVerificationCodeService;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailVerificationPromptController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private EmailVerificationCodeService $codes,
    ) {}

    /**
     * Show the email verification prompt page.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended($this->authService->continueUrlAfterAuth($user));
        }

        return Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
            'email' => $user->email,
            'expireMinutes' => $this->codes->expireMinutes(),
            'hasLiveCode' => $this->codes->hasLiveCode($user),
            'resendAvailableIn' => $this->codes->resendAvailableIn($user),
        ]);
    }
}
