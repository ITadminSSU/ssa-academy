<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use App\Services\LegalAgreementService;
use App\Services\SignWellService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LegalAgreementController extends Controller
{
    public function __construct(
        private LegalAgreementService $legalAgreement,
        private AuthService $authService,
        private SignWellService $signWell,
    ) {}

    public function show(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $this->legalAgreement->requiresAcceptance($user)) {
            return redirect($this->authService->homeUrlFor($user));
        }

        return Inertia::render('legal/agreement', [
            'document' => $this->legalAgreement->documentPayload($user),
            'signwellEnabled' => $this->signWell->isEnabled(),
            'signwellStatus' => $user->signwell_status,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if ($this->signWell->isEnabled()) {
            return redirect()->route('signwell.start');
        }

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
