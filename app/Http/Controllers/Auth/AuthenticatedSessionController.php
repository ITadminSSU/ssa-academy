<?php

namespace App\Http\Controllers\Auth;

use Inertia\Inertia;
use Inertia\Response;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use App\Services\LegalAgreementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private LegalAgreementService $legalAgreement,
    ) {}

    /**
     * Show the login page.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect($this->authService->homeUrlFor($request->user()));
        }

        if ($request->filled('redirect') && $this->isSafeRedirect($request->query('redirect'))) {
            $request->session()->put('url.intended', $request->query('redirect'));
        }

        $authStatus = $this->authService->googleAuthStatus();
        $recaptchaStatus = $this->authService->recaptchaStatus();

        return Inertia::render('auth/login', [
            'status' => $request->session()->get('status'),
            'canResetPassword' => Route::has('password.request'),
            'googleLogIn' => $authStatus['authStatus'],
            'recaptcha' => $recaptchaStatus,
            'testAccounts' => $this->shouldShowTestAccounts() ? [
                ['role' => 'Admin', 'email' => 'admin-test@smartsourcingusa.com', 'password' => 'password123'],
                ['role' => 'Trainer', 'email' => 'trainer-test@smartsourcingusa.com', 'password' => 'password123'],
                ['role' => 'Internal employee', 'email' => 'employee-test@smartsourcingusa.com', 'password' => 'password123'],
            ] : null,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse|SymfonyResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $destination = $this->resolvePostLoginDestination($request);

        if ($request->header('X-Inertia')) {
            return Inertia::location($destination);
        }

        return redirect()->to($destination);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        if ($user = $request->user()) {
            $user->tokens()->delete();
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function resolvePostLoginDestination(Request $request): string
    {
        $user = $request->user();

        if ($user && $this->legalAgreement->requiresAcceptance($user)) {
            $request->session()->forget('url.intended');

            return route('legal.agreement.show');
        }

        // Land on the Home tab by default for learners (internal/external).
        // homeUrlFor() already defaults to the 'home' tab; admins/trainers are unaffected.
        $fallback = $this->authService->homeUrlFor($user);
        $intended = $request->session()->pull('url.intended');

        if ($this->isSafeRedirect($intended) && !$this->isAuthScreen($intended)) {
            return $intended;
        }

        return $fallback;
    }

    private function isSafeRedirect(?string $url): bool
    {
        if (!$url) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return true;
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        return $appUrl !== '' && str_starts_with($url, $appUrl);
    }

    private function isAuthScreen(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? $url;

        return in_array($path, ['/login', '/register', '/forgot-password'], true)
            || str_starts_with($path, '/password-reset');
    }

    private function shouldShowTestAccounts(): bool
    {
        if (app()->environment('local')) {
            return true;
        }

        return app()->environment('staging')
            && filter_var(env('SSU_SHOW_TEST_ACCOUNTS', false), FILTER_VALIDATE_BOOL);
    }
}
