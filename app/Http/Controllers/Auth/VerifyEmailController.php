<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendRegistrationNotificationsJob;
use App\Services\AuthService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __construct(private AuthService $authService) {}

    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
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
