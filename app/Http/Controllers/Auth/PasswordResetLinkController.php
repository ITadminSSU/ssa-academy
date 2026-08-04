<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Models\User;
use App\Services\AccountMailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function __construct(private AccountMailService $accountMail) {}

    /**
     * Show the password reset link request page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        config(['app.frontend_url' => config('app.url')]);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            try {
                $token = Password::createToken($user);
                $this->accountMail->sendPasswordResetLink($user, $token);
            } catch (\Throwable $exception) {
                Log::error('Account password reset email failed', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $exception->getMessage(),
                ]);

                return back()->with(
                    'error',
                    'We could not send the password reset email. Please verify mail settings (Resend API recommended) or try again later.'
                );
            }
        }

        return back()->with('success', __('A reset link will be sent if the account exists.'));
    }

    /**
     * Handle an incoming change password request.
     */
    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->password = Hash::make($request->password);
        $user->save();

        return back()->with('success', 'Password successfully changed.');
    }
}
