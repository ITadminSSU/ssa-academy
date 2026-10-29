<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmailVerificationNotificationJob;
use App\Models\User;
use App\Services\Auth\SingleSessionService;
use App\Services\AuthService;
use App\Services\LearnerTypeResolver;
use App\Services\LegalAgreementService;
use App\Support\PersonName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class RegisteredUserController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private LearnerTypeResolver $learnerTypeResolver,
        private LegalAgreementService $legalAgreement,
        private SingleSessionService $singleSession,
    ) {}

    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        $authStatus = $this->authService->googleAuthStatus();
        $recaptchaStatus = $this->authService->recaptchaStatus();

        return Inertia::render('auth/register', [
            'googleLogIn' => $authStatus['authStatus'],
            'recaptcha' => $recaptchaStatus,
            'legalDocument' => $this->legalAgreement->documentPayload(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse|SymfonyResponse
    {
        $validated = $request->validate([
            'given_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'recaptcha_status' => 'required|boolean',
            'recaptcha' => 'nullable|captcha|required_if:recaptcha_status,true',
            'accept_terms' => 'accepted',
            'accept_legal_age' => 'accepted',
        ], [
            'given_name.required' => 'Please enter your given name.',
            'accept_terms.accepted' => 'You must agree to the website Terms and Conditions.',
            'accept_legal_age.accepted' => 'You must confirm that you are of legal age and capable of entering into these Terms.',
        ]);

        if (! PersonName::fits($validated['given_name'], $validated['last_name'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'last_name' => 'The given name and last name together must be 255 characters or fewer.',
            ]);
        }

        // Profile answers, CV, ID, and the student agreement are collected later.
        // Do not record a legal acceptance at account creation.
        $user = User::create([
            ...PersonName::attributes($validated['given_name'], $validated['last_name'] ?? null),
            'email' => $validated['email'],
            'role' => 'student',
            'user_type' => $this->learnerTypeResolver->resolveFromEmail($validated['email'])->value,
            'status' => 1,
            'password' => Hash::make($validated['password']),
        ]);

        $verificationError = null;

        try {
            SendEmailVerificationNotificationJob::dispatchSync($user->id);
        } catch (\Throwable $exception) {
            $verificationError = 'We created your account but could not send the verification code. Click Resend below, and check your spam folder if it still does not arrive.';
            Log::error('Email verification send failed during registration', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('auth.remember');
        $this->singleSession->claim($user, false);

        $redirect = redirect()->route('verification.notice');

        if ($verificationError) {
            return $redirect->with('error', $verificationError);
        }

        return $redirect->with('status', 'verification-code-sent');
    }
}
