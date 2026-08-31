<?php

use App\Services\Payment\LaunchOfferEnrollmentService;
use App\Services\Payment\StripeCustomerService;
use App\Services\Payment\StripeWebhookService;
use App\Services\Payment\SubscriptionService;
use App\Support\StripeCheckoutIds;
use Modules\PaymentGateways\Services\PaymentService;

function makePaidCheckoutSession(array $overrides = []): object
{
    $defaults = [
        'id' => 'cs_test_session',
        'mode' => 'payment',
        'payment_status' => 'paid',
        'payment_intent' => null,
        'amount_total' => 9900,
        'client_reference_id' => '10',
        'metadata' => (object) [
            'user_id' => '10',
            'item_type' => 'course',
            'item_id' => '5',
            'launch_offer_mode' => 'legacy_one_time',
        ],
    ];

    $session = array_merge($defaults, $overrides);
    if (isset($overrides['metadata']) && is_array($overrides['metadata'])) {
        $session['metadata'] = (object) array_merge((array) $defaults['metadata'], $overrides['metadata']);
    }

    return (object) $session;
}

function makeWebhookService(?PaymentService $payment = null): StripeWebhookService
{
    return new StripeWebhookService(
        Mockery::mock(StripeCustomerService::class),
        Mockery::mock(SubscriptionService::class),
        Mockery::mock(LaunchOfferEnrollmentService::class),
        $payment ?? Mockery::mock(PaymentService::class),
    );
}

it('enrolls a paid one-time session when payment_intent is null by using the session id', function () {
    $payment = Mockery::mock(PaymentService::class);
    $payment->shouldReceive('coursesBuy')
        ->once()
        ->withArgs(function (
            string $method,
            string $itemType,
            string $itemId,
            string $transactionId,
            float $taxAmount,
            float $totalPrice,
            ?string $couponCode,
            ?string $userId,
        ) {
            return $method === 'stripe'
                && $itemType === 'course'
                && $itemId === '5'
                && $transactionId === 'cs_test_session'
                && $taxAmount === 0.0
                && $totalPrice === 99.0
                && $couponCode === null
                && $userId === '10';
        });

    $enrolled = makeWebhookService($payment)->enrollOneTimeFromCheckoutSession(makePaidCheckoutSession());

    expect($enrolled)->toBeTrue();
});

it('does not enroll when no transaction id can be resolved', function () {
    $payment = Mockery::mock(PaymentService::class);
    $payment->shouldNotReceive('coursesBuy');

    $enrolled = makeWebhookService($payment)->enrollOneTimeFromCheckoutSession(makePaidCheckoutSession([
        'id' => null,
        'payment_intent' => null,
    ]));

    expect($enrolled)->toBeFalse();
});

it('does not enroll deposit checkout sessions as one-time purchases', function () {
    $payment = Mockery::mock(PaymentService::class);
    $payment->shouldNotReceive('coursesBuy');

    $enrolled = makeWebhookService($payment)->enrollOneTimeFromCheckoutSession(makePaidCheckoutSession([
        'metadata' => ['launch_offer_mode' => 'deposit'],
    ]));

    expect($enrolled)->toBeFalse();
});

it('reuses the same transaction id when webhook enrollment runs twice', function () {
    $transactionIds = [];
    $payment = Mockery::mock(PaymentService::class);
    $payment->shouldReceive('coursesBuy')
        ->twice()
        ->andReturnUsing(function (...$args) use (&$transactionIds) {
            $transactionIds[] = $args[3];
        });

    $session = makePaidCheckoutSession([
        'id' => 'cs_test_once',
        'payment_intent' => null,
    ]);
    $webhooks = makeWebhookService($payment);

    expect($webhooks->enrollOneTimeFromCheckoutSession($session))->toBeTrue();
    expect($webhooks->enrollOneTimeFromCheckoutSession($session))->toBeTrue();
    expect($transactionIds)->toBe(['cs_test_once', 'cs_test_once']);
    expect(StripeCheckoutIds::transactionId($session))->toBe('cs_test_once');
});
