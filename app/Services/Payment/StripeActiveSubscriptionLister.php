<?php

namespace App\Services\Payment;

use Stripe\Subscription;

/**
 * Reads Stripe subscriptions whose status is active. This class never creates,
 * updates, or cancels a Stripe object.
 */
class StripeActiveSubscriptionLister
{
    public function __construct(private StripeCustomerService $stripe) {}

    /**
     * @return list<array{
     *     stripe_subscription_id: string,
     *     status: string,
     *     cancel_at_period_end: bool,
     *     current_period_end: int|null,
     *     user_id: int|null,
     *     course_id: int|null,
     *     customer_email: string,
     *     customer_name: string
     * }>
     */
    public function list(): array
    {
        try {
            $this->stripe->configureStripe();
        } catch (\Throwable $exception) {
            throw new StripeActiveSubscriptionException('Stripe could not be reached, so the active subscription list is unavailable.', 0, $exception);
        }

        $rows = [];
        $startingAfter = null;
        $pages = 0;
        $hasMore = false;

        try {
            do {
                $params = [
                    'status' => 'active',
                    'limit' => 100,
                    'expand' => ['data.customer'],
                ];

                if ($startingAfter) {
                    $params['starting_after'] = $startingAfter;
                }

                $page = Subscription::all($params);
                $pages++;

                foreach ($page->data as $subscription) {
                    if ((string) ($subscription->status ?? '') !== 'active') {
                        continue;
                    }

                    $rows[] = $this->normalize($subscription);
                    $startingAfter = (string) $subscription->id;
                }

                $hasMore = (bool) ($page->has_more ?? false);
            } while ($hasMore && $startingAfter && $pages < 100);
        } catch (StripeActiveSubscriptionException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new StripeActiveSubscriptionException('Stripe could not be reached, so the active subscription list is unavailable.', 0, $exception);
        }

        if ($hasMore && $pages >= 100) {
            throw new StripeActiveSubscriptionException('Stripe returned more active subscriptions than this page can show.');
        }

        return $rows;
    }

    /**
     * @return array{
     *     stripe_subscription_id: string,
     *     status: string,
     *     cancel_at_period_end: bool,
     *     current_period_end: int|null,
     *     user_id: int|null,
     *     course_id: int|null,
     *     customer_email: string,
     *     customer_name: string
     * }
     */
    private function normalize(object $subscription): array
    {
        $metadata = [];

        if (isset($subscription->metadata)) {
            $metadata = method_exists($subscription->metadata, 'toArray')
                ? $subscription->metadata->toArray()
                : (array) $subscription->metadata;
        }

        $customer = $subscription->customer ?? null;
        $email = '';
        $name = '';

        if (is_object($customer)) {
            $email = trim((string) ($customer->email ?? ''));
            $name = trim((string) ($customer->name ?? ''));
        }

        $periodEnd = $subscription->current_period_end ?? null;

        if (! $periodEnd && isset($subscription->items->data[0]->current_period_end)) {
            $periodEnd = $subscription->items->data[0]->current_period_end;
        }

        return [
            'stripe_subscription_id' => (string) $subscription->id,
            'status' => 'active',
            'cancel_at_period_end' => (bool) ($subscription->cancel_at_period_end ?? false),
            'current_period_end' => is_numeric($periodEnd) ? (int) $periodEnd : null,
            'user_id' => $this->positiveInt($metadata['user_id'] ?? null),
            'course_id' => $this->positiveInt($metadata['course_id'] ?? null),
            'customer_email' => $email,
            'customer_name' => $name,
        ];
    }

    private function positiveInt(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
