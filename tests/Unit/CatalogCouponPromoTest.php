<?php

use App\Models\Course\CourseCoupon;
use App\Services\Course\CourseCouponService;

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
