<?php

namespace App\Http\Middleware;

use App\Services\LegalAgreementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLegalAgreementAccepted
{
    public function __construct(private LegalAgreementService $legalAgreement) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$this->legalAgreement->requiresAcceptance($user)) {
            return $next($request);
        }

        if ($request->routeIs(
            'legal.agreement.*',
            'signwell.*',
            'logout',
            'verification.*',
            'password.*',
        )) {
            return $next($request);
        }

        if ($request->header('X-Inertia')) {
            return redirect()->route('legal.agreement.show');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'You must complete the student agreement before continuing.',
                'redirect' => route('legal.agreement.show'),
            ], 403);
        }

        if (app(\App\Services\SignWellService::class)->isEnabled()) {
            return redirect()->route('legal.agreement.show');
        }

        return redirect()->route('legal.agreement.show');
    }
}
