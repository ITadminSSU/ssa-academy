<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmailVerificationNotificationJob;
use App\Models\ProfessionalType;
use App\Models\User;
use App\Services\Auth\SingleSessionService;
use App\Services\AuthService;
use App\Services\LearnerTypeResolver;
use App\Services\LegalAgreementService;
use App\Services\SignWellService;
use App\Support\RegistrationProfileOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
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
        private SignWellService $signWell,
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
            'estimatingSoftwareOptions' => RegistrationProfileOptions::estimatingSoftware(),
            'constructionExperienceOptions' => RegistrationProfileOptions::constructionExperience(),
            'signwellEnabled' => $this->signWell->isEnabled(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse|SymfonyResponse
    {
        $softwareOptions = RegistrationProfileOptions::estimatingSoftware();
        $experienceOptions = RegistrationProfileOptions::constructionExperience();

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'recaptcha_status' => 'required|boolean',
            'recaptcha' => 'nullable|captcha|required_if:recaptcha_status,true',
            'professional_type_id' => 'required|exists:professional_types,id',
            'professional_type_other' => 'nullable|string|max:255',
            'estimating_software' => 'required|array|min:1',
            'estimating_software.*' => ['string', Rule::in($softwareOptions)],
            'estimating_software_other' => 'nullable|string|max:255',
            'construction_experience' => ['required', 'string', Rule::in($experienceOptions)],
            'worked_as_construction_va' => 'required|boolean',
            'cv_resume' => 'required|file|mimes:pdf,doc,docx|max:10240',
            'referred_by' => 'nullable|string|max:255',
            'referrer_is_employee' => 'nullable|in:0,1',
            'accept_terms' => 'accepted',
            'accept_legal_age' => 'accepted',
            'accept_single_account' => 'accepted',
            'accept_student_integrity' => 'accepted',
        ];

        if ($request->professional_type_id) {
            $professionalType = ProfessionalType::find($request->professional_type_id);
            if ($professionalType && strtolower($professionalType->name) === 'other') {
                $rules['professional_type_other'] = 'required|string|max:255';
            }
        }

        $selectedSoftware = array_values(array_filter((array) $request->input('estimating_software', [])));
        if (in_array('Others', $selectedSoftware, true)) {
            $rules['estimating_software_other'] = 'required|string|max:255';
        }

        $validated = $request->validate($rules, [
            'professional_type_id.required' => 'Please select your professional type.',
            'estimating_software.required' => 'Please select at least one estimating software option.',
            'estimating_software.min' => 'Please select at least one estimating software option.',
            'estimating_software_other.required' => 'Please specify the other estimating software you have used.',
            'construction_experience.required' => 'Please select your years of construction experience.',
            'worked_as_construction_va.required' => 'Please indicate whether you have worked as a Construction Virtual Assistant.',
            'cv_resume.required' => 'Please upload your CV or resume.',
            'cv_resume.mimes' => 'CV / resume must be a PDF, DOC, or DOCX file.',
            'cv_resume.max' => 'CV / resume must not be larger than 10MB.',
            'accept_terms.accepted' => 'You must agree to the website Terms and Conditions.',
            'accept_legal_age.accepted' => 'You must confirm that you are of legal age and capable of entering into these Terms.',
            'accept_single_account.accepted' => 'You must confirm that you understand the one-account / no-sharing rule.',
            'accept_student_integrity.accepted' => $this->signWell->isEnabled()
                ? 'You must confirm that you will also review and accept the Student Integrity, Confidentiality, and Participation Agreement (via SignWell).'
                : 'You must confirm that you will also review and accept the Student Integrity, Confidentiality, and Participation Agreement.',
        ]);

        if (in_array('None', $selectedSoftware, true)) {
            $selectedSoftware = ['None'];
            $validated['estimating_software_other'] = null;
        } elseif (! in_array('Others', $selectedSoftware, true)) {
            $validated['estimating_software_other'] = null;
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => 'student',
            'user_type' => $this->learnerTypeResolver->resolveFromEmail($validated['email'])->value,
            'status' => 1,
            'password' => Hash::make($validated['password']),
            'professional_type_id' => $validated['professional_type_id'],
            'professional_type_other' => $validated['professional_type_other'] ?? null,
            'estimating_software' => $selectedSoftware,
            'estimating_software_other' => $validated['estimating_software_other'] ?? null,
            'construction_experience' => $validated['construction_experience'],
            'worked_as_construction_va' => (bool) $validated['worked_as_construction_va'],
            'referred_by' => filled($validated['referred_by'] ?? null)
                ? trim((string) $validated['referred_by'])
                : null,
            'referrer_is_employee' => filled($validated['referred_by'] ?? null)
                && array_key_exists('referrer_is_employee', $validated)
                && $validated['referrer_is_employee'] !== null
                && $validated['referrer_is_employee'] !== ''
                    ? (bool) (int) $validated['referrer_is_employee']
                    : null,
        ]);

        $user->addMediaFromRequest('cv_resume')
            ->withCustomProperties(['name' => 'cv_resume'])
            ->toMediaCollection('cv_resume');

        // When SignWell is enabled, e-sign replaces immediate checkbox acceptance.
        if (! $this->signWell->isEnabled()) {
            $this->legalAgreement->recordAcceptance($user, $request, false);
        }

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
        $this->singleSession->claim($user);

        $redirect = redirect()->route('verification.notice');

        if ($verificationError) {
            return $redirect->with('error', $verificationError);
        }

        return $redirect->with('status', 'verification-code-sent');
    }
}
