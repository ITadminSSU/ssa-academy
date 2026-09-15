<?php

use App\Support\StripeInvoiceIds;

it('reads a legacy invoice subscription id', function () {
    $invoice = (object) [
        'id' => 'in_legacy',
        'subscription' => 'sub_legacy',
        'payment_intent' => 'pi_legacy',
        'billing_reason' => 'subscription_create',
    ];

    expect(StripeInvoiceIds::subscriptionId($invoice))->toBe('sub_legacy')
        ->and(StripeInvoiceIds::transactionId($invoice))->toBe('pi_legacy')
        ->and(StripeInvoiceIds::billingReason($invoice))->toBe('subscription_create');
});

it('reads a basil invoice subscription from parent.subscription_details', function () {
    $invoice = (object) [
        'id' => 'in_basil',
        'subscription' => null,
        'payment_intent' => null,
        'parent' => (object) [
            'type' => 'subscription_details',
            'subscription_details' => (object) [
                'subscription' => 'sub_basil',
                'billing_reason' => 'subscription_create',
            ],
        ],
        'payments' => (object) [
            'data' => [
                (object) [
                    'payment' => (object) [
                        'payment_intent' => 'pi_from_payments',
                    ],
                ],
            ],
        ],
    ];

    expect(StripeInvoiceIds::subscriptionId($invoice))->toBe('sub_basil')
        ->and(StripeInvoiceIds::transactionId($invoice))->toBe('pi_from_payments')
        ->and(StripeInvoiceIds::billingReason($invoice))->toBe('subscription_create')
        ->and(StripeInvoiceIds::lookupIds($invoice))->toContain('pi_from_payments', 'in_basil');
});

it('falls back to the invoice id when no payment intent exists', function () {
    $invoice = (object) [
        'id' => 'in_only',
        'subscription' => 'sub_1',
        'payment_intent' => null,
    ];

    expect(StripeInvoiceIds::transactionId($invoice))->toBe('in_only');
});

it('returns empty ids for a missing invoice', function () {
    expect(StripeInvoiceIds::subscriptionId(null))->toBe('')
        ->and(StripeInvoiceIds::transactionId(null))->toBe('');
});
