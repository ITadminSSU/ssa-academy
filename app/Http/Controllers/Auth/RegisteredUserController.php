<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendRegistrationNotificationsJob;
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
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

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
    public function store(Request $request): RedirectResponse
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
            'accept_terms' => 'accepted',
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
            'accept_terms.accepted' => $this->signWell->isEnabled()
                ? 'You must agree to the Terms & Conditions and complete the Student Agreement to create an account.'
                : 'You must agree to the Terms & Conditions to create an account.',
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
            'email_verified_at' => now(),
        ]);

        $user->addMediaFromRequest('cv_resume')
            ->withCustomProperties(['name' => 'cv_resume'])
            ->toMediaCollection('cv_resume');

        // When SignWell is enabled, e-sign replaces immediate checkbox acceptance.
        if (! $this->signWell->isEnabled()) {
            $this->legalAgreement->recordAcceptance($user, $request, false);
        }

        SendRegistrationNotificationsJob::dispatch($user->id)
            ->onConnection('sync')
            ->afterResponse();

        Auth::login($user);
        $request->session()->regenerate();
        $this->singleSession->claim($user);

        if ($this->signWell->isEnabled()) {
            try {
                $signingUrl = $this->signWell->startSigning($user);

                return \Inertia\Inertia::location($signingUrl);
            } catch (\Throwable $e) {
                return redirect()
                    ->route('legal.agreement.show')
                    ->with('error', $e->getMessage());
            }
        }

        return redirect()->intended($this->authService->homeUrlFor($user));
    }
}
