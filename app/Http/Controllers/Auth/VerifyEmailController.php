<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendRegistrationNotificationsJob;
use App\Services\Auth\EmailVerificationCodeService;
use App\Services\AuthService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private EmailVerificationCodeService $codes,
    ) {}

    /**
     * Confirm the 6-digit email verification code.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:12'],
        ]);

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended($this->authService->continueUrlAfterAuth($user));
        }

        $result = $this->codes->attempt($user, (string) $request->input('code'));

        if (! $result['ok']) {
            return back()->withErrors(['code' => $result['message']]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $user = $user->fresh();

        if ($user->legal_agreement_accepted_at && ! $user->legal_confirmation_email_sent_at) {
            SendRegistrationNotificationsJob::dispatch($user->id)
                ->onConnection('sync')
                ->afterResponse();
        }

        $destination = $this->authService->continueUrlAfterVerification($user);

        return redirect()
            ->intended($destination)
            ->with('success', 'Your email is verified. You can continue.');
    }
}
