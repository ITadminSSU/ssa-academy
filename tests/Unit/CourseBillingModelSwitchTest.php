<?php

use App\Http\Requests\UpdateCourseRequest;
use Illuminate\Validation\ValidationException;

function billingSwitchRequest(array $overrides = []): UpdateCourseRequest
{
    $request = UpdateCourseRequest::create('/courses/update', 'POST', array_merge([
        'tab' => 'pricing',
        'pricing_type' => 'paid',
        'billing_model' => 'one_time',
        'price' => 99,
        'expiry_type' => 'lifetime',
        'discount' => false,
        'launch_offer_enabled' => false,
        'catalog_coupon_promo' => false,
    ], $overrides));

    $request->headers->set('Accept', 'application/json');
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    return $request;
}

function leftoverLaunchFields(): array
{
    return [
        'launch_offer_enabled' => true,
        'subscription_price' => 1,
        'launch_offer_starts_at' => '2026-08-15T00:00',
        'launch_offer_ends_at' => '2026-09-09T06:05',
        'launch_list_price' => 99,
        'launch_offer_price' => 99,
        'launch_deposit_amount' => 20,
        'launch_balance_amount' => 79,
        'launch_full_upfront_price' => 99,
    ];
}

it('clears a leftover launch offer when switching to one-time purchase', function () {
    $request = billingSwitchRequest([
        'billing_model' => 'one_time',
        'price' => 99,
        ...leftoverLaunchFields(),
    ]);

    $request->validateResolved();

    expect($request->input('billing_model'))->toBe('one_time');
    expect($request->boolean('launch_offer_enabled'))->toBeFalse();
    expect($request->input('launch_offer_starts_at'))->toBeNull();
    expect($request->input('subscription_price'))->toBeNull();
});

it('clears a leftover launch offer when switching to monthly subscription', function () {
    $request = billingSwitchRequest([
        'billing_model' => 'subscription',
        'price' => null,
        ...leftoverLaunchFields(),
    ]);

    $request->validateResolved();

    expect($request->input('billing_model'))->toBe('subscription');
    expect($request->boolean('launch_offer_enabled'))->toBeFalse();
    expect($request->input('launch_offer_starts_at'))->toBeNull();
    expect((float) $request->input('subscription_price'))->toBe(1.0);
});

it('clears a leftover launch offer when switching to upfront plus monthly', function () {
    $request = billingSwitchRequest([
        'billing_model' => 'upfront_subscription',
        'price' => 99,
        ...leftoverLaunchFields(),
    ]);

    $request->validateResolved();

    expect($request->input('billing_model'))->toBe('upfront_subscription');
    expect($request->boolean('launch_offer_enabled'))->toBeFalse();
    expect($request->input('launch_offer_starts_at'))->toBeNull();
    expect((float) $request->input('price'))->toBe(99.0);
    expect((float) $request->input('subscription_price'))->toBe(1.0);
});

it('keeps launch offer enabled for pre-registration billing', function () {
    $request = billingSwitchRequest([
        'billing_model' => 'pre_register_subscription',
        'price' => null,
        ...leftoverLaunchFields(),
    ]);

    $request->validateResolved();

    expect($request->input('billing_model'))->toBe('pre_register_subscription');
    expect($request->boolean('launch_offer_enabled'))->toBeTrue();
    expect($request->input('launch_offer_starts_at'))->not->toBeNull();
});

it('still requires launch dates for pre-registration billing', function () {
    $request = billingSwitchRequest([
        'billing_model' => 'pre_register_subscription',
        'price' => null,
        'subscription_price' => 1,
        'launch_offer_enabled' => true,
    ]);

    expect(fn () => $request->validateResolved())->toThrow(ValidationException::class);
});
