<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use App\Services\LegalAgreementService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LegalAgreementController extends Controller
{
    public function __construct(
        private LegalAgreementService $legalAgreement,
        private AuthService $authService,
    ) {}

    public function show(Request $request)
    {
        $user = $request->user();

        if (!$user || !$this->legalAgreement->requiresAcceptance($user)) {
            return redirect($this->authService->homeUrlFor($user));
        }

        return Inertia::render('legal/agreement', [
            'document' => $this->legalAgreement->documentPayload(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'accept_terms' => 'accepted',
        ], [
            'accept_terms.accepted' => 'You must agree to the Terms & Conditions to continue.',
        ]);

        $this->legalAgreement->recordAcceptance($user, $request);

        return redirect()
            ->intended($this->authService->homeUrlFor($user))
            ->with('success', 'Terms & Conditions accepted. Your academy access is now provisioned.');
    }
}
