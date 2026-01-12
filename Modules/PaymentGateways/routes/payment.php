<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Modules\PaymentGateways\Http\Middleware\SSLConfigMiddleware;
use Modules\PaymentGateways\Http\Controllers\Payment\PaymentController;
use Modules\PaymentGateways\Http\Controllers\Payment\MollieController;
use Modules\PaymentGateways\Http\Controllers\Payment\PaypalController;
use Modules\PaymentGateways\Http\Controllers\Payment\PaystackController;
use Modules\PaymentGateways\Http\Controllers\Payment\SslCommerzController;
use Modules\PaymentGateways\Http\Controllers\Payment\StripeController;
use Modules\PaymentGateways\Http\Controllers\Payment\OfflineController;
use Modules\PaymentGateways\Http\Controllers\Payment\PaymentReportController;
use Modules\PaymentGateways\Http\Controllers\Payment\RazorpayController;

Route::middleware(['auth', 'role:admin'])->prefix('dashboard')->group(function () {
    // payment gateway settings
    Route::controller(PaymentController::class)->prefix('settings/payment')->group(function () {
        Route::get('/', 'payment')->name('settings.payment');
        Route::post('/{id}', 'payment_update')->name('settings.payment.update');
    });

    // payment reports routes
    Route::controller(PaymentReportController::class)->prefix('payment-reports')->group(function () {
        Route::get('online', 'online_index')->name('payment-reports.online.index');
        Route::get('offline', 'offline_index')->name('payment-reports.offline.index');
        Route::post('offline/{id}/verify', 'verify')->name('payment-reports.offline.verify');
        Route::post('offline/{id}/reject', 'reject')->name('payment-reports.offline.reject');
    });
});

Route::middleware(['auth', 'verified'])->prefix('payments')->group(function () {
    Route::get('{from}/{item}/{id}', [PaymentController::class, 'index'])->name('payments.index');

    Route::post('paypal/payment', [PaypalController::class, 'payment'])->name('payments.paypal.payment');
    Route::get('paypal/success', [PaypalController::class, 'success'])->name('payments.paypal.success');
    Route::get('paypal/cancel', [PaypalController::class, 'cancel'])->name('payments.paypal.cancel');

    Route::post('stripe/payment', [StripeController::class, 'payment'])->name('payments.stripe.payment');
    Route::get('stripe/success', [StripeController::class, 'success'])->name('payments.stripe.success');
    Route::get('stripe/cancel', [StripeController::class, 'cancel'])->name('payments.stripe.cancel');

    Route::post('mollie/payment', [MollieController::class, 'payment'])->name('payments.mollie.payment');
    Route::get('mollie/success', [MollieController::class, 'success'])->name('payments.mollie.success');

    Route::get('paystack/redirect', [PaystackController::class, 'paystack_redirect'])->name('payments.paystack.redirect');
    Route::get('paystack/callback', [PaystackController::class, 'verify_transaction'])->name('payments.paystack.callback');

    Route::post('razorpay/redirect', [RazorpayController::class, 'index'])->name('razorpay.redirect');
    Route::post('razorpay/payment', [RazorpayController::class, 'payment'])->name('razorpay.payment');

    Route::get('offline/redirect', [OfflineController::class, 'index'])->name('payments.offline.redirect');
    Route::post('offline/submit', [OfflineController::class, 'submit'])->name('payments.offline.submit');
    Route::get('offline/cancel', [OfflineController::class, 'cancel'])->name('payments.offline.cancel');
});

Route::withoutMiddleware([VerifyCsrfToken::class])->middleware([SSLConfigMiddleware::class])->prefix('payments/sslcommerz')->group(function () {
    Route::post('/payment', [SslCommerzController::class, 'index'])->name('sslcommerz.payment');

    Route::post('/success', [SslCommerzController::class, 'success']);
    Route::post('/fail', [SslCommerzController::class, 'fail']);
    Route::post('/cancel', [SslCommerzController::class, 'cancel']);

    Route::post('/ipn', [SslCommerzController::class, 'ipn']);
});
