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
            'logout',
            'verification.*',
            'password.*',
        )) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'You must accept the Terms & Conditions and NDA before continuing.',
                'redirect' => route('legal.agreement.show'),
            ], 403);
        }

        return redirect()->route('legal.agreement.show');
    }
}
