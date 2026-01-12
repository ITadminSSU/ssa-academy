<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Modules\PaymentGateways\Http\Middleware\SSLConfigMiddleware;
use Modules\PaymentGateways\Http\Controllers\Payout\PayoutController;
use Modules\PaymentGateways\Http\Controllers\Payout\MollieController;
use Modules\PaymentGateways\Http\Controllers\Payout\PaypalController;
use Modules\PaymentGateways\Http\Controllers\Payout\PaystackController;
use Modules\PaymentGateways\Http\Controllers\Payout\StripeController;
use Modules\PaymentGateways\Http\Controllers\Payout\SslCommerzController;
use Modules\PaymentGateways\Http\Controllers\Payout\RazorpayController;

Route::middleware(['auth', 'verified', 'role:admin,instructor'])->group(function () {
   Route::middleware('checkCourseCreation')->prefix('dashboard')->group(function () {
      Route::resource('payouts', PayoutController::class)->only(['index', 'store', 'destroy']);
      Route::get('payouts/settings', [PayoutController::class, 'settings_index'])->name('payouts.settings.index');
      Route::post('payouts/settings', [PayoutController::class, 'settings_update'])->name('payouts.settings.update');

      Route::get('payouts/request', [PayoutController::class, 'request_index'])->name('payouts.request.index');
      Route::get('payouts/history', [PayoutController::class, 'history_index'])->name('payouts.history.index');
   });

   Route::prefix('payouts')->group(function () {
      Route::post('paypal/payment', [PaypalController::class, 'payment'])->name('payouts.paypal.payment');
      Route::get('paypal/success', [PaypalController::class, 'success'])->name('payouts.paypal.success');
      Route::get('paypal/cancel', [PaypalController::class, 'cancel'])->name('payouts.paypal.cancel');

      Route::post('stripe/payment', [StripeController::class, 'payment'])->name('payouts.stripe.payment');
      Route::get('stripe/success', [StripeController::class, 'success'])->name('payouts.stripe.success');
      Route::get('stripe/cancel', [StripeController::class, 'cancel'])->name('payouts.stripe.cancel');

      Route::post('mollie/payment', [MollieController::class, 'payment'])->name('payouts.mollie.payment');
      Route::get('mollie/success', [MollieController::class, 'success'])->name('payouts.mollie.success');

      Route::get('paystack/redirect', [PaystackController::class, 'paystack_redirect'])->name('payouts.paystack.redirect');
      Route::get('paystack/callback', [PaystackController::class, 'verify_transaction'])->name('payouts.paystack.callback');

      Route::post('razorpay/redirect', [RazorpayController::class, 'index'])->name('payouts.razorpay.redirect');
      Route::post('razorpay/payment', [RazorpayController::class, 'payment'])->name('payouts.razorpay.payment');

      Route::get('{slug}/{request_id}', [PayoutController::class, 'checkout'])->name('payouts.gateway.index');
   });
});

Route::withoutMiddleware([VerifyCsrfToken::class])->middleware([SSLConfigMiddleware::class])->prefix('payouts/sslcommerz')->group(function () {
   Route::post('/payment', [SslCommerzController::class, 'index'])->name('payouts.sslcommerz.payment');

   Route::post('/success', [SslCommerzController::class, 'success']);
   Route::post('/fail', [SslCommerzController::class, 'fail']);
   Route::post('/cancel', [SslCommerzController::class, 'cancel']);

   Route::post('/ipn', [SslCommerzController::class, 'ipn']);
});
