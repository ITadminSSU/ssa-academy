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

        if (! $user) {
            return redirect()->route('login');
        }

        // Recover students who signed in SignWell but never got marked complete in DB.
        if ($this->signWell->isEnabled()) {
            try {
                if ($this->signWell->syncCompletionFromSignWell($user->fresh(), $request->ip())) {
                    return redirect()
                        ->intended($this->authService->homeUrlFor($user->fresh()))
                        ->with('success', 'Student agreement signed. Welcome to SMARTSOURCING USA Academy.');
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('SignWell sync on agreement page failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! $this->legalAgreement->requiresAcceptance($user->fresh())) {
            return redirect($this->authService->homeUrlFor($user));
        }

        return Inertia::render('legal/agreement', [
            'document' => $this->legalAgreement->documentPayload($user),
            'signwellEnabled' => $this->signWell->isEnabled(),
            'signwellStatus' => $user->fresh()->signwell_status,
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
            'accept_legal_age' => 'accepted',
            'accept_single_account' => 'accepted',
            'accept_student_integrity' => 'accepted',
        ], [
            'accept_terms.accepted' => 'You must agree to the website Terms and Conditions.',
            'accept_legal_age.accepted' => 'You must confirm that you are of legal age and capable of entering into these Terms.',
            'accept_single_account.accepted' => 'You must confirm that you understand the one-account / no-sharing rule.',
            'accept_student_integrity.accepted' => 'You must confirm that you will also review and accept the Student Integrity, Confidentiality, and Participation Agreement.',
        ]);

        $this->legalAgreement->recordAcceptance($user, $request);

        return redirect()
            ->intended($this->authService->homeUrlFor($user))
            ->with('success', 'Terms & Conditions accepted. Your academy access is now provisioned.');
    }
}
