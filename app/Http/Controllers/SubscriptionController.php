<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use App\Services\Payment\StripeCustomerService;
use App\Services\Payment\SubscriptionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private StripeCustomerService $stripeCustomer,
        private AuthService $authService,
    ) {}

    public function portal(Request $request): Response
    {
        $user = $request->user();

        if (!$this->stripeCustomer->isStripeActive()) {
            return back()->with('error', 'Online billing is not available right now.');
        }

        if (!$this->subscriptionService->userCanManageBilling($user)) {
            return back()->with('error', 'You do not have any subscriptions to manage.');
        }

        try {
            $returnUrl = config('payment.subscription.portal_return_url')
                ?: $this->authService->homeUrlFor($user, ['tab' => 'subscriptions']);

            $portalUrl = $this->stripeCustomer->createBillingPortalSession($user, $returnUrl);

            if ($request->header('X-Inertia')) {
                return Inertia::location($portalUrl);
            }

            return redirect()->away($portalUrl);
        } catch (\Throwable $exception) {
            report($exception);

            $message = str_contains(strtolower($exception->getMessage()), 'configuration')
                ? 'Stripe Customer Portal is not configured yet. Ask an admin to enable it in the Stripe Dashboard (Settings → Billing → Customer portal).'
                : 'Unable to open the billing portal. Please try again or contact support.';

            return back()->with('error', $message);
        }
    }
}
