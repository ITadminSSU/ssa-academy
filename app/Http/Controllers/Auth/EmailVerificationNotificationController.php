<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateEmailRequest;
use App\Jobs\SendEmailVerificationNotificationJob;
use App\Models\User;
use App\Services\AccountService;
use App\Services\Auth\EmailVerificationCodeService;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EmailVerificationNotificationController extends Controller
{
    public function __construct(
        private AccountService $accountService,
        private AuthService $authService,
    ) {}

    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended($this->authService->continueUrlAfterAuth($request->user()));
        }

        $codes = app(EmailVerificationCodeService::class);

        if (! $codes->canResend($request->user())) {
            $seconds = $codes->resendAvailableIn($request->user());

            return back()->with(
                'error',
                "Please wait {$seconds} seconds before requesting a new code."
            );
        }

        try {
            SendEmailVerificationNotificationJob::dispatchSync($request->user()->id);
        } catch (\Throwable $exception) {
            Log::error('Email verification resend failed', [
                'user_id' => $request->user()->id,
                'email' => $request->user()->email,
                'error' => $exception->getMessage(),
            ]);

            return back()->with(
                'error',
                'We could not send the verification email just now. Please try again in a minute. If this keeps happening, contact training@smartsourcingusa.com.'
            );
        }

        return back()->with('status', 'verification-code-sent');
    }

    /**
     * Send a new email for verify the new email.
     */
    public function update(UpdateEmailRequest $request)
    {
        try {
            $this->accountService->changeEmail($request->validated(), (string) Auth::id());
        } catch (\Throwable $exception) {
            Log::error('Account email change verification failed', [
                'user_id' => Auth::id(),
                'error' => $exception->getMessage(),
            ]);

            return back()->with(
                'error',
                'We could not send the verification email. Please verify mail settings (Resend API recommended) or try again later.'
            );
        }

        return back()->with(
            'success',
            'We sent a verification link to your new email and a security alert to your current email. Check both inboxes (and spam). After you confirm, you will need to log in with the new email.'
        );
    }

    /**
     * Confirm a pending email change (login not required; validated via DB token).
     */
    public function save(Request $request): RedirectResponse
    {
        $userId = $request->query('user');
        $token = $request->query('token');

        if (! is_numeric($userId) || ! is_string($token) || $token === '') {
            return redirect()->route('login')
                ->with('error', 'This verification link is invalid. Please request a new email change link.');
        }

        $saved = $this->accountService->saveChangedEmail($token, (string) $userId);
        $user = User::query()->find((int) $userId);

        if (! $saved || ! $user) {
            Log::warning('Email change confirmation failed', [
                'user_id' => $userId,
                'token_prefix' => substr($token, 0, 8),
            ]);

            return redirect()->route('login')
                ->with('error', 'This verification link is invalid or has expired. Please request a new email change link.');
        }

        $this->accountService->invalidateUserSessions((int) $user->id);

        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')
            ->with('success', 'Your email address has been updated. Please log in with your new email address.');
    }
}
