<?php

namespace App\Support;

class StripeInvoiceIds
{
    public static function subscriptionId(?object $invoice): string
    {
        if (! $invoice) {
            return '';
        }

        $direct = StripeCheckoutIds::objectId($invoice->subscription ?? null);
        if ($direct !== '') {
            return $direct;
        }

        $parent = $invoice->parent ?? null;
        if (is_object($parent)) {
            $nested = StripeCheckoutIds::objectId($parent->subscription_details->subscription ?? null);
            if ($nested !== '') {
                return $nested;
            }
        }

        return StripeCheckoutIds::objectId(data_get($invoice, 'parent.subscription_details.subscription'));
    }

    public static function transactionId(?object $invoice): string
    {
        if (! $invoice) {
            return '';
        }

        $paymentIntent = StripeCheckoutIds::objectId($invoice->payment_intent ?? null);
        if ($paymentIntent !== '') {
            return $paymentIntent;
        }

        foreach (self::paymentIntentCandidates($invoice) as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return StripeCheckoutIds::objectId($invoice->id ?? null);
    }

    public static function invoiceId(?object $invoice): string
    {
        return StripeCheckoutIds::objectId($invoice->id ?? null);
    }

    public static function billingReason(?object $invoice): string
    {
        if (! $invoice) {
            return '';
        }

        $reason = trim((string) ($invoice->billing_reason ?? ''));
        if ($reason !== '') {
            return $reason;
        }

        return trim((string) data_get($invoice, 'parent.subscription_details.billing_reason', ''));
    }

    /**
     * @return list<string>
     */
    public static function lookupIds(?object $invoice): array
    {
        return array_values(array_filter(array_unique([
            self::transactionId($invoice),
            self::invoiceId($invoice),
            ...self::paymentIntentCandidates($invoice),
        ])));
    }

    /**
     * @return list<string>
     */
    private static function paymentIntentCandidates(object $invoice): array
    {
        $ids = [];
        $payments = data_get($invoice, 'payments.data', []);

        if (! is_array($payments) && ! is_iterable($payments)) {
            return $ids;
        }

        foreach ($payments as $payment) {
            $ids[] = StripeCheckoutIds::objectId(data_get($payment, 'payment.payment_intent'));
            $ids[] = StripeCheckoutIds::objectId(data_get($payment, 'payment_intent'));
        }

        return array_values(array_filter($ids));
    }
}
