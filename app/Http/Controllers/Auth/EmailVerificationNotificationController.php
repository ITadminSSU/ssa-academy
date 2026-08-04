<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateEmailRequest;
use App\Jobs\SendEmailVerificationNotificationJob;
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

        return back()->with('success', 'We have sent a verification link to your new email address.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function save(Request $request)
    {
        $user = Auth::user();
        $saved = $this->accountService->saveChangedEmail($request->token, $user->id);
        $flash = $saved ? 'success' : 'error';
        $message = $saved ? 'New email successfully changed.' : 'Verification link is invalid or has expired. Please request a new email change link.';

        if ($user->role == 'student') {
            return redirect()->to($this->authService->homeUrlFor($user, ['tab' => 'settings']))
                ->with($flash, $message);
        }

        return redirect()->route('settings.account')
            ->with($flash, $message);
    }
}
