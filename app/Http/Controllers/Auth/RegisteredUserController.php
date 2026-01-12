<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ProfessionalType;
use App\Services\AuthService;
use Illuminate\Auth\Events\Registered;
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
    public function __construct(private AuthService $authService) {}

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
            'professional_type_id' => 'nullable|exists:professional_types,id',
            'professional_type_other' => 'nullable|string|max:255',
            'cv_resume' => 'nullable|file|mimes:pdf,doc,docx|max:10240', // 10MB max
        ];

        // If professional_type_id is provided, check if it's "Other"
        if ($request->professional_type_id) {
            $professionalType = ProfessionalType::find($request->professional_type_id);
            if ($professionalType && strtolower($professionalType->name) === 'other') {
                $rules['professional_type_other'] = 'required|string|max:255';
            }
        }

        $request->validate($rules);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role' => 'student',
            'status' => 1,
            'password' => Hash::make($request->password),
            'professional_type_id' => $request->professional_type_id,
            'professional_type_other' => $request->professional_type_other,
        ]);

        // Handle CV/Resume upload
        if ($request->hasFile('cv_resume')) {
            $user->addMediaFromRequest('cv_resume')
                ->withCustomProperties(['name' => 'cv_resume'])
                ->toMediaCollection('cv_resume');
        }

        // Check if SMTP is configured before sending verification email
        $smtpConfigured = $this->isSmtpConfigured();
        
        if ($smtpConfigured) {
            try {
        event(new Registered($user));
            } catch (\Exception $e) {
                // If email sending fails, auto-verify the email to allow registration
                Log::warning('Email verification failed during registration, auto-verifying user', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
                
                $user->email_verified_at = now();
                $user->save();
            }
        } else {
            // If SMTP is not configured, auto-verify the email to allow registration
            $user->email_verified_at = now();
            $user->save();
            
            Log::info('User registered without email verification due to missing SMTP configuration', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        }

        Auth::login($user);

        // return to_route('dashboard');
        return redirect()->route('student.index', ['tab' => 'courses']);
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
