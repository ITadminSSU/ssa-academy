<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use App\Services\LegalAgreementService;
use App\Services\SignWellService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SignWellController extends Controller
{
    public function __construct(
        private SignWellService $signWell,
        private LegalAgreementService $legalAgreement,
        private AuthService $authService,
    ) {}

    /**
     * Start or resume SignWell signing and redirect the student.
     */
    public function start(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $this->legalAgreement->requiresAcceptance($user)) {
            return redirect($this->authService->homeUrlFor($user));
        }

        if (! $this->signWell->isEnabled()) {
            return redirect()
                ->route('legal.agreement.show')
                ->with('error', 'Electronic signing is not available right now.');
        }

        try {
            $url = $this->signWell->startSigning($user);

            return redirect()->away($url);
        } catch (\Throwable $e) {
            Log::warning('SignWell start failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('legal.agreement.show')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Return URL after the student finishes (or closes) SignWell.
     */
    public function complete(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Prefer webhook as source of truth; if already completed, go to dashboard.
        if (! $this->legalAgreement->requiresAcceptance($user->fresh())) {
            return redirect()
                ->intended($this->authService->homeUrlFor($user))
                ->with('success', 'Student agreement signed. Welcome to SMARTSOURCING USA Academy.');
        }

        if ($user->signwell_document_id) {
            $document = $this->signWell->getDocument($user->signwell_document_id);

            if ($this->signWell->documentIsCompleted($document)) {
                $this->signWell->markCompleted($user, $user->signwell_document_id, $request->ip());

                return redirect()
                    ->intended($this->authService->homeUrlFor($user->fresh()))
                    ->with('success', 'Student agreement signed. Welcome to SMARTSOURCING USA Academy.');
            }
        }

        return redirect()
            ->route('legal.agreement.show')
            ->with('info', 'Please finish signing the student agreement to continue.');
    }
}
