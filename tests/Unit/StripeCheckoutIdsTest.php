<?php

use App\Support\StripeCheckoutIds;

it('prefers a payment intent string as the transaction id', function () {
    $session = (object) [
        'id' => 'cs_test_session',
        'payment_intent' => 'pi_test_123',
        'subscription' => null,
    ];

    expect(StripeCheckoutIds::transactionId($session))->toBe('pi_test_123');
});

it('uses an expanded payment intent object id', function () {
    $session = (object) [
        'id' => 'cs_test_session',
        'payment_intent' => (object) ['id' => 'pi_expanded'],
        'subscription' => null,
    ];

    expect(StripeCheckoutIds::transactionId($session))->toBe('pi_expanded');
});

it('falls back to the checkout session id when payment_intent is null', function () {
    $session = (object) [
        'id' => 'cs_test_session',
        'payment_intent' => null,
        'subscription' => null,
    ];

    expect(StripeCheckoutIds::transactionId($session))->toBe('cs_test_session');
});

it('returns an empty string when no stripe ids are present', function () {
    $session = (object) [
        'id' => null,
        'payment_intent' => null,
        'subscription' => null,
    ];

    expect(StripeCheckoutIds::transactionId($session))->toBe('');
    expect(StripeCheckoutIds::transactionId(null))->toBe('');
});

it('prefers the success url session_id over the temp store id', function () {
    expect(StripeCheckoutIds::sessionIdFromRequest('cs_from_query', 'cs_from_temp'))
        ->toBe('cs_from_query');
    expect(StripeCheckoutIds::sessionIdFromRequest(null, 'cs_from_temp'))
        ->toBe('cs_from_temp');
    expect(StripeCheckoutIds::sessionIdFromRequest('', null))
        ->toBe('');
});

it('includes an unencoded checkout session placeholder in the success url', function () {
    expect(StripeCheckoutIds::successUrl())
        ->toContain('session_id={CHECKOUT_SESSION_ID}')
        ->not->toContain('%7BCHECKOUT_SESSION_ID%7D');
});
