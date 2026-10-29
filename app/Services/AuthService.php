<?php

namespace App\Services;

use App\Enums\LearnerUserType;
use App\Enums\UserType;
use App\Models\User;
use App\Support\PersonName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(
        private LearnerTypeResolver $learnerTypeResolver,
    ) {}
    public function recaptchaStatus()
    {
        $recaptchaStatus = false;
        $status = config('captcha.status');
        $siteKey = config('captcha.sitekey');
        $secretKey = config('captcha.secret');

        if ($status && $siteKey && $secretKey) {
            $recaptchaStatus = true;
        }

        return [
            'status' => $recaptchaStatus,
            'siteKey' => $siteKey,
            'secretKey' => $secretKey,
        ];
    }

    public function googleAuthStatus()
    {
        $authStatus = false;
        $googleStatus = config('services.google.status');
        $clientID = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');
        $redirectURI = config('services.google.redirect');

        if ($googleStatus && !empty($clientID) && !empty($clientSecret) && !empty($redirectURI)) {
            $authStatus = true;
        }

        return [
            'authStatus' => $authStatus,
            'clientID' => $clientID,
            'clientSecret' => $clientSecret,
            'redirectURI' => $redirectURI,
        ];
    }

    public function googleAuthRegister(object $user): User
    {
        return DB::transaction(function () use ($user) {
            [$given, $last] = PersonName::split((string) $user->name);

            $userData = [
                ...PersonName::attributes($given, $last),
                'email' => $user->email,
                'photo' => $user->avatar,
                'role' => UserType::STUDENT->value,
                'user_type' => $this->learnerTypeResolver->resolveFromEmail($user->email)->value,
                'email_verified_at' => now(),
                'google_id' => $user->id,
                'password' => Hash::make('googleauth'),
            ];

            // Add OAuth tokens if available
            if (isset($user->token)) {
                $userData['google_access_token'] = $user->token;

                if (isset($user->refreshToken)) {
                    $userData['google_refresh_token'] = $user->refreshToken;
                }

                if (isset($user->expiresIn)) {
                    $userData['google_token_expires_in'] = now()->addSeconds($user->expiresIn);
                }
            }

            $newUser = User::create($userData);

            return $newUser;
        }, 5);
    }

    /**
     * Update Google OAuth tokens for existing user
     */
    public function updateGoogleTokens(User $existingUser, object $googleUser): void
    {
        $updateData = [];

        if (isset($googleUser->token)) {
            $updateData['google_access_token'] = $googleUser->token;
        }

        if (isset($googleUser->refreshToken)) {
            $updateData['google_refresh_token'] = $googleUser->refreshToken;
        }

        if (isset($googleUser->expiresIn)) {
            $updateData['google_token_expires_in'] = now()->addSeconds($googleUser->expiresIn);
        }

        if (!empty($updateData)) {
            $existingUser->update($updateData);
        }
    }

    public function dashboardRouteNameFor(?User $user): string
    {
        if (!$user) {
            return 'login';
        }

        return match ($user->role) {
            UserType::ADMIN->value => 'dashboard.admin',
            UserType::INSTRUCTOR->value => 'dashboard.trainer',
            UserType::SOCIAL_MEDIA->value => 'dashboard.social',
            UserType::STUDENT->value => $user->isEmployeeLearner()
                ? 'dashboard.internal'
                : 'dashboard.external',
            default => 'login',
        };
    }

    public function homeUrlFor(?User $user, array $parameters = []): string
    {
        if (!$user) {
            return route('login');
        }

        $routeName = $this->dashboardRouteNameFor($user);

        if ($routeName === 'login') {
            return route('login');
        }

        if (in_array($routeName, ['dashboard.internal', 'dashboard.external'], true)) {
            $parameters = array_merge(['tab' => 'home'], $parameters);
        }

        return route($routeName, $parameters);
    }

    public function needsEmailVerification(?User $user): bool
    {
        return $user instanceof User && ! $user->hasVerifiedEmail();
    }

    /**
     * Where a signed-in user should go next: verify email, then the dashboard.
     * The student agreement is opened from the first course start, not from login.
     */
    public function continueUrlAfterAuth(?User $user, array $parameters = []): string
    {
        if (! $user) {
            return route('login');
        }

        if ($this->needsEmailVerification($user)) {
            return route('verification.notice');
        }

        return $this->homeUrlFor($user, $parameters);
    }

    public function continueUrlAfterVerification(User $user): string
    {
        return $this->homeUrlFor($user).'?verified=1';
    }

    /**
     * After the agreement is signed, return to the course the student was starting.
     */
    public function urlAfterLegalAcceptance(User $user): string
    {
        $courseId = session()->pull('pending_course_start_id');

        if ($courseId) {
            return route('player.resume-after-agreement', ['course' => $courseId]);
        }

        return $this->homeUrlFor($user);
    }
}
