<?php

use App\Enums\CourseBillingModel;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course\Course;
use App\Models\Course\CourseCoupon;
use App\Services\Course\CourseCouponService;
use Illuminate\Validation\ValidationException;

function pricingPromoRequest(array $overrides = []): UpdateCourseRequest
{
    $request = UpdateCourseRequest::create('/courses/update', 'POST', array_merge([
        'tab' => 'pricing',
        'pricing_type' => 'paid',
        'billing_model' => 'one_time',
        'price' => 75,
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

it('takes a catalog coupon off remaining, not the deposit', function () {
    $service = app(CourseCouponService::class);
    $coupon = new CourseCoupon([
        'discount_type' => 'fixed',
        'discount' => 29,
    ]);

    $deposit = 20.0;
    $remaining = 79.0;
    $discount = $service->discountForAmount($coupon, $remaining);
    $remainingWithCoupon = round($remaining - $discount, 2);
    $totalWithCoupon = round($deposit + $remainingWithCoupon, 2);

    expect($discount)->toBe(29.0);
    expect($remainingWithCoupon)->toBe(50.0);
    expect($totalWithCoupon)->toBe(70.0);
});

it('builds catalog display amounts from a pricing remaining discount', function () {
    $service = app(CourseCouponService::class);
    $amounts = $service->amountsForFixedOffRemaining(20, 79, 99, 29);

    expect($amounts['discount_amount'])->toBe(29.0);
    expect($amounts['balance_with_coupon'])->toBe(50.0);
    expect($amounts['total_with_coupon'])->toBe(70.0);
    expect($amounts['full_upfront_with_coupon'])->toBe(70.0);
});

it('caps a catalog coupon so remaining cannot go below zero', function () {
    $service = app(CourseCouponService::class);
    $coupon = new CourseCoupon([
        'discount_type' => 'fixed',
        'discount' => 100,
    ]);

    expect($service->discountForAmount($coupon, 79))->toBe(79.0);
});

it('applies a percent catalog coupon to remaining only', function () {
    $service = app(CourseCouponService::class);
    $coupon = new CourseCoupon([
        'discount_type' => 'percentage',
        'discount' => 20,
    ]);

    expect($service->discountForAmount($coupon, 79))->toBe(15.8);
});

it('allows a catalog coupon promo on a one-time paid course', function () {
    $request = pricingPromoRequest([
        'catalog_coupon_promo' => true,
        'catalog_coupon_off_remaining' => 29,
    ]);

    $request->validateResolved();

    expect($request->boolean('catalog_coupon_promo'))->toBeTrue();
    expect((float) $request->input('catalog_coupon_off_remaining'))->toBe(29.0);
});

it('allows a catalog coupon promo on an upfront subscription course', function () {
    $request = pricingPromoRequest([
        'billing_model' => 'upfront_subscription',
        'price' => 75,
        'subscription_price' => 6,
        'catalog_coupon_promo' => true,
        'catalog_coupon_off_remaining' => 29,
    ]);

    $request->validateResolved();

    expect($request->boolean('catalog_coupon_promo'))->toBeTrue();
    expect((float) $request->input('catalog_coupon_off_remaining'))->toBe(29.0);
});

it('does not require a launch remaining amount for a one-time catalog coupon', function () {
    $request = pricingPromoRequest([
        'catalog_coupon_promo' => true,
        'catalog_coupon_off_remaining' => 29,
        'launch_balance_amount' => null,
    ]);

    $request->validateResolved();

    expect($request->boolean('catalog_coupon_promo'))->toBeTrue();
});

it('rejects a one-time catalog coupon that is not less than the price', function () {
    $request = pricingPromoRequest([
        'catalog_coupon_promo' => true,
        'catalog_coupon_off_remaining' => 75,
    ]);

    expect(fn () => $request->validateResolved())->toThrow(ValidationException::class);
});

it('clears catalog coupon promo for monthly-only billing', function () {
    $request = pricingPromoRequest([
        'billing_model' => 'subscription',
        'subscription_price' => 6,
        'price' => null,
        'catalog_coupon_promo' => true,
        'catalog_coupon_off_remaining' => 29,
    ]);

    $request->validateResolved();

    expect($request->boolean('catalog_coupon_promo'))->toBeFalse();
    expect($request->input('catalog_coupon_off_remaining'))->toBeNull();
});

it('builds a one-time catalog promo from the pricing display amount', function () {
    $course = new Course([
        'billing_model' => CourseBillingModel::ONE_TIME,
        'price' => 75,
        'catalog_coupon_promo' => true,
        'catalog_coupon_off_remaining' => 29,
        'launch_offer_enabled' => false,
    ]);

    $promo = app(CourseCouponService::class)->catalogPromoFor($course);

    expect($promo)->not->toBeNull();
    expect($promo['kind'])->toBe('one_time');
    expect($promo['list_price'])->toBe(75.0);
    expect($promo['total_with_coupon'])->toBe(46.0);
    expect($promo['subscription_price'])->toBe(0.0);
});

it('takes the catalog coupon off enrollment only for upfront subscription', function () {
    $course = new Course([
        'billing_model' => CourseBillingModel::UPFRONT_SUBSCRIPTION,
        'price' => 75,
        'subscription_price' => 6,
        'catalog_coupon_promo' => true,
        'catalog_coupon_off_remaining' => 29,
        'launch_offer_enabled' => false,
    ]);

    $promo = app(CourseCouponService::class)->catalogPromoFor($course);

    expect($promo)->not->toBeNull();
    expect($promo['kind'])->toBe('upfront');
    expect($promo['total_with_coupon'])->toBe(46.0);
    expect($promo['subscription_price'])->toBe(6.0);
});

it('does not advertise a catalog coupon on monthly-only billing', function () {
    $course = new Course([
        'billing_model' => CourseBillingModel::SUBSCRIPTION,
        'subscription_price' => 6,
        'catalog_coupon_promo' => true,
        'catalog_coupon_off_remaining' => 29,
        'launch_offer_enabled' => false,
    ]);

    expect(app(CourseCouponService::class)->catalogPromoFor($course))->toBeNull();
});
