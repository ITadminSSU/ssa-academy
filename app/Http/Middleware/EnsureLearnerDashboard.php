<?php

namespace App\Http\Middleware;

use App\Enums\LearnerUserType;
use App\Enums\UserType;
use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLearnerDashboard
{
    public function __construct(private AuthService $authService) {}

    /**
     * @param  'internal'|'external'  $dashboardType
     */
    public function handle(Request $request, Closure $next, string $dashboardType): Response
    {
        $user = $request->user();

        if (!$user || $user->role !== UserType::STUDENT->value) {
            return redirect($this->authService->homeUrlFor($user));
        }

        $expectedType = $dashboardType === 'internal'
            ? LearnerUserType::EMPLOYEE
            : LearnerUserType::EXTERNAL;

        if ($user->user_type !== $expectedType) {
            return redirect($this->authService->homeUrlFor($user))
                ->with('error', 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
