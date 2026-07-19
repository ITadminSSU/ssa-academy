<?php

namespace App\Services\Payment;

use App\Models\User;
use App\Services\SettingsService;
use Stripe\BillingPortal\Session;
use Stripe\Customer;
use Stripe\Stripe;

class StripeCustomerService
{
    public function __construct(private SettingsService $settingsService) {}

    /**
     * Return the persisted Stripe customer id, creating a Stripe Customer when needed.
     */
    public function findOrCreateCustomer(User $user): string
    {
        if (!empty($user->stripe_customer_id)) {
            return $user->stripe_customer_id;
        }

        $this->configureStripe();

        $customer = Customer::create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => [
                'user_id' => (string) $user->id,
            ],
        ]);

        $user->forceFill(['stripe_customer_id' => $customer->id])->save();

        return $customer->id;
    }

    /**
     * Retrieve an existing Stripe customer id without creating one.
     */
    public function getCustomerId(User $user): ?string
    {
        return $user->stripe_customer_id ?: null;
    }

    /**
     * Ensure the local user record matches an existing Stripe customer id.
     */
    public function attachCustomerId(User $user, string $stripeCustomerId): string
    {
        if ($user->stripe_customer_id !== $stripeCustomerId) {
            $user->forceFill(['stripe_customer_id' => $stripeCustomerId])->save();
        }

        return $stripeCustomerId;
    }

    public function configureStripe(): void
    {
        Stripe::setApiKey($this->resolveSecretKey());
    }

    public function resolveSecretKey(): string
    {
        $stripe = $this->settingsService->getSetting(['type' => 'payment', 'sub_type' => 'stripe']);

        if (!$stripe || empty($stripe->fields)) {
            throw new \RuntimeException('Stripe is not configured.');
        }

        $testMode = (bool) ($stripe->fields['test_mode'] ?? true);

        $secret = $testMode
            ? ($stripe->fields['test_secret_key'] ?? null)
            : ($stripe->fields['live_secret_key'] ?? null);

        if (empty($secret)) {
            throw new \RuntimeException('Stripe secret key is missing.');
        }

        return $secret;
    }

    public function isStripeActive(): bool
    {
        $stripe = $this->settingsService->getSetting(['type' => 'payment', 'sub_type' => 'stripe']);

        return (bool) ($stripe?->fields['active'] ?? false);
    }

    public function createBillingPortalSession(User $user, string $returnUrl): string
    {
        $this->configureStripe();

        $customerId = $this->findOrCreateCustomer($user);

        $session = Session::create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);

        return $session->url;
    }
}
