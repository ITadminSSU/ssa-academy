<?php

namespace App\Services\Payment;

use App\Enums\CoursePricingType;
use App\Models\Course\Course;
use App\Services\SettingsService;
use Illuminate\Support\Str;
use Stripe\Price;
use Stripe\Product;

class CourseStripeSyncService
{
    public function __construct(
        private StripeCustomerService $stripeCustomer,
        private SettingsService $settingsService,
    ) {}

    public function sync(Course $course): Course
    {
        if (!$this->stripeCustomer->isStripeActive()) {
            throw new \RuntimeException('Stripe is not active. Enable Stripe in payment settings first.');
        }

        if ($course->pricing_type !== CoursePricingType::PAID->value) {
            throw new \RuntimeException('Only paid courses can be synced to Stripe.');
        }

        if (!$course->usesSubscriptionBilling()) {
            throw new \RuntimeException('Switch the billing model to monthly subscription before syncing.');
        }

        $amount = $course->subscriptionCheckoutPrice();

        if (!$amount || $amount < 1) {
            throw new \RuntimeException('Set a monthly subscription price of at least 1 before syncing.');
        }

        $this->stripeCustomer->configureStripe();

        $currency = strtolower($this->resolveCurrency());
        $unitAmount = (int) round($amount * 100);
        $productId = $this->ensureProduct($course);

        if ($this->priceMatches($course->stripe_price_id, $unitAmount, $currency)) {
            $course->update(['stripe_product_id' => $productId]);

            return $course->fresh();
        }

        if ($course->stripe_price_id) {
            try {
                Price::update($course->stripe_price_id, ['active' => false]);
            } catch (\Throwable) {
                // Older price may already be inactive or removed in Stripe.
            }
        }

        $price = Price::create([
            'product' => $productId,
            'unit_amount' => $unitAmount,
            'currency' => $currency,
            'recurring' => ['interval' => 'month'],
            'metadata' => [
                'course_id' => (string) $course->id,
            ],
        ]);

        $course->update([
            'stripe_product_id' => $productId,
            'stripe_price_id' => $price->id,
        ]);

        return $course->fresh();
    }

    public function isSynced(Course $course): bool
    {
        return $course->usesSubscriptionBilling()
            && !empty($course->stripe_product_id)
            && !empty($course->stripe_price_id);
    }

    private function ensureProduct(Course $course): string
    {
        $metadata = ['course_id' => (string) $course->id];
        $description = Str::limit(strip_tags((string) ($course->short_description ?? '')), 500) ?: null;

        if ($course->stripe_product_id) {
            try {
                Product::update($course->stripe_product_id, [
                    'name' => $course->title,
                    'description' => $description,
                    'metadata' => $metadata,
                    'active' => true,
                ]);

                return $course->stripe_product_id;
            } catch (\Throwable) {
                // Product missing in Stripe — create a new one below.
            }
        }

        $product = Product::create([
            'name' => $course->title,
            'description' => $description,
            'metadata' => $metadata,
        ]);

        return $product->id;
    }

    private function priceMatches(?string $priceId, int $unitAmount, string $currency): bool
    {
        if (!$priceId) {
            return false;
        }

        try {
            $existing = Price::retrieve($priceId);

            return $existing->active
                && (int) $existing->unit_amount === $unitAmount
                && strtolower((string) $existing->currency) === $currency
                && ($existing->recurring->interval ?? null) === 'month';
        } catch (\Throwable) {
            return false;
        }
    }

    private function resolveCurrency(): string
    {
        $stripe = $this->settingsService->getSetting(['type' => 'payment', 'sub_type' => 'stripe']);
        $currency = $stripe?->fields['currency'] ?? null;

        if ($currency) {
            return (string) $currency;
        }

        $system = app('system_settings');

        return $system->fields['selling_currency'] ?? 'USD';
    }
}
