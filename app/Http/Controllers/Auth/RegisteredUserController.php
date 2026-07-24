<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ProfessionalType;
use App\Services\AuthService;
use App\Services\LearnerTypeResolver;
use App\Services\LegalAgreementService;
use App\Jobs\SendRegistrationNotificationsJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private LearnerTypeResolver $learnerTypeResolver,
        private LegalAgreementService $legalAgreement,
    ) {}

    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        $authStatus = $this->authService->googleAuthStatus();
        $recaptchaStatus = $this->authService->recaptchaStatus();
        $professionalTypes = ProfessionalType::where('is_active', true)->orderBy('sort_order')->get();

        return Inertia::render('auth/register', [
            'googleLogIn' => $authStatus['authStatus'],
            'recaptcha' => $recaptchaStatus,
            'professionalTypes' => $professionalTypes,
            'legalDocument' => $this->legalAgreement->documentPayload(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'recaptcha_status' => 'required|boolean',
            'recaptcha' => 'nullable|captcha|required_if:recaptcha_status,true',
            'professional_type_id' => 'required|exists:professional_types,id',
            'professional_type_other' => 'nullable|string|max:255',
            'cv_resume' => 'required|file|mimes:pdf,doc,docx|max:10240',
            'accept_terms' => 'accepted',
            'accept_nda' => 'accepted',
        ];

        // If professional_type_id is provided, check if it's "Other"
        if ($request->professional_type_id) {
            $professionalType = ProfessionalType::find($request->professional_type_id);
            if ($professionalType && strtolower($professionalType->name) === 'other') {
                $rules['professional_type_other'] = 'required|string|max:255';
            }
        }

        $request->validate($rules, [
            'professional_type_id.required' => 'Please select your professional type.',
            'cv_resume.required' => 'Please upload your CV or resume.',
            'cv_resume.mimes' => 'CV / resume must be a PDF, DOC, or DOCX file.',
            'cv_resume.max' => 'CV / resume must not be larger than 10MB.',
            'accept_terms.accepted' => 'You must agree to the Terms & Conditions to create an account.',
            'accept_nda.accepted' => 'You must agree to the Non-Disclosure Agreement to create an account.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role' => 'student',
            'user_type' => $this->learnerTypeResolver->resolveFromEmail($request->email)->value,
            'status' => 1,
            'password' => Hash::make($request->password),
            'professional_type_id' => $request->professional_type_id,
            'professional_type_other' => $request->professional_type_other,
        ]);

        // Handle CV/Resume upload
        $user->addMediaFromRequest('cv_resume')
            ->withCustomProperties(['name' => 'cv_resume'])
            ->toMediaCollection('cv_resume');

        $smtpConfigured = $this->isSmtpConfigured();

        if (! $smtpConfigured) {
            $user->email_verified_at = now();
            $user->save();

            Log::info('User registered without email verification due to missing SMTP configuration', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        }

        // Record legal acceptance in the database only; emails are sent after the HTTP response.
        $this->legalAgreement->recordAcceptance($user, $request, false);

        if ($smtpConfigured) {
            SendRegistrationNotificationsJob::dispatch($user->id)
                ->onConnection('sync')
                ->afterResponse();
        }

        Auth::login($user);

        return redirect()->intended($this->authService->homeUrlFor($user));
    }

    /**
     * Check if SMTP is properly configured
     */
    private function isSmtpConfigured(): bool
    {
        if (config('mail.default') !== 'smtp') {
            return false;
        }

        $required = [
            config('mail.mailers.smtp.host'),
            config('mail.mailers.smtp.port'),
            config('mail.mailers.smtp.username'),
            config('mail.mailers.smtp.password'),
            config('mail.from.address'), // Check for "From" address
        ];

        // All required fields must be present and non-empty
        foreach ($required as $field) {
            if (empty($field)) {
                return false;
            }
        }

        return true;
    }
}
