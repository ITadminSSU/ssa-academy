<?php

namespace App\Support;

class StripeCheckoutIds
{
    public static function successUrl(): string
    {
        return route('payments.stripe.success').'?session_id={CHECKOUT_SESSION_ID}';
    }

    public static function sessionIdFromRequest(mixed $querySessionId, mixed $tempStripeId): string
    {
        $fromQuery = trim((string) $querySessionId);
        if ($fromQuery !== '') {
            return $fromQuery;
        }

        return trim((string) $tempStripeId);
    }

    public static function transactionId(?object $session): string
    {
        if (! $session) {
            return '';
        }

        $paymentIntentId = self::objectId($session->payment_intent ?? null);
        if ($paymentIntentId !== '') {
            return $paymentIntentId;
        }

        $subscriptionId = self::objectId($session->subscription ?? null);
        if ($subscriptionId !== '') {
            return $subscriptionId;
        }

        return self::objectId($session->id ?? null);
    }

    private static function objectId(mixed $value): string
    {
        if (is_object($value)) {
            return trim((string) ($value->id ?? ''));
        }

        if (is_string($value) || is_int($value)) {
            return trim((string) $value);
        }

        return '';
    }
}
