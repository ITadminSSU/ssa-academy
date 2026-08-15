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

        // Recover stuck users who already signed in SignWell but DB save failed.
        if ($this->signWell->syncCompletionFromSignWell($user->fresh(), $request->ip())) {
            return redirect()
                ->intended($this->authService->homeUrlFor($user->fresh()))
                ->with('success', 'Student agreement signed. Welcome to SMARTSOURCING USA Academy.');
        }

        if (! $this->legalAgreement->requiresAcceptance($user->fresh())) {
            return redirect($this->authService->homeUrlFor($user));
        }

        if (! $this->signWell->isEnabled()) {
            return redirect()
                ->route('legal.agreement.show')
                ->with('error', 'Electronic signing is not available right now.');
        }

        try {
            $url = $this->signWell->startSigning($user->fresh());

            return Inertia::location($url);
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

        $user = $user->fresh();

        if (! $this->legalAgreement->requiresAcceptance($user)) {
            return redirect()
                ->intended($this->authService->homeUrlFor($user))
                ->with('success', 'Student agreement signed. Welcome to SMARTSOURCING USA Academy.');
        }

        $redirectSaysCompleted = strtolower((string) $request->query('document_status', '')) === 'completed';

        try {
            $synced = $this->signWell->syncCompletionFromSignWell(
                $user,
                $request->ip(),
                trustRedirectCompleted: $redirectSaysCompleted,
            );
        } catch (\Throwable $e) {
            Log::error('SignWell complete sync failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('legal.agreement.show')
                ->with('error', 'Your signature was received but we could not unlock your account yet. Please contact support or try again. ('.$e->getMessage().')');
        }

        if ($synced) {
            return redirect()
                ->intended($this->authService->homeUrlFor($user->fresh()))
                ->with('success', 'Student agreement signed. Welcome to SMARTSOURCING USA Academy.');
        }

        return redirect()
            ->route('legal.agreement.show')
            ->with('info', 'Please finish signing the student agreement to continue. If you already signed, click Sign Student Agreement again.');
    }
}
