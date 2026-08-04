<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateEmailRequest;
use App\Jobs\SendEmailVerificationNotificationJob;
use App\Models\User;
use App\Services\AccountService;
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
            return redirect()->intended($this->authService->homeUrlFor($request->user()));
        }

        SendEmailVerificationNotificationJob::dispatch($request->user()->id)
            ->onConnection('sync')
            ->afterResponse();

        return back()->with('status', 'verification-link-sent');
    }

    /**
     * Send a new email for verify the new email.
     */
    public function update(UpdateEmailRequest $request)
    {
        try {
            $this->accountService->changeEmail($request->validated(), Auth::user()->id);
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
            'We sent a verification link to your new email address. Check your inbox and spam folder, then click the link to confirm the change.'
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

        if (Auth::check() && Auth::id() === $user->id) {
            if ($user->role === 'student') {
                return redirect()->to($this->authService->homeUrlFor($user, ['tab' => 'settings']))
                    ->with('success', 'Your email address has been updated successfully.');
            }

            return redirect()->route('settings.account', ['tab' => 'change-email'])
                ->with('success', 'Your email address has been updated successfully.');
        }

        return redirect()->route('login')
            ->with('success', 'Your email address has been updated. Please log in with your new email address.');
    }
}
