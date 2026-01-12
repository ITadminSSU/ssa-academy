<?php

namespace Modules\PaymentGateways\Http\Controllers\Payment;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Modules\PaymentGateways\Http\Requests\GatewayRequest;
use Modules\PaymentGateways\Services\PaymentService;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $payment,
        private SettingsService $settings,
    ) {}

    public function index(Request $request, string $from, string $item_type, string $id)
    {
        $payments = $this->settings->getSettings(['type' => 'payment']);
        $currency = app('system_settings')->fields['selling_currency'] ?? 'USD';
        $checkoutItem = $this->payment->getCheckoutItem($item_type, $id, $request->coupon);
        $itemCoupons = $this->payment->validateExamCoupons($item_type, $id);

        return view('paymentgateways::payment', [
            'id' => $id,
            'from' => $from,
            'coupon' => $request->coupon,
            'item_type' => $item_type,
            'payments' => $payments,
            'currency' => $currency,
            'itemCoupons' => $itemCoupons,
            ...$checkoutItem,
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function payment(Request $request)
    {
        $payments = $this->settings->getSettings(['type' => 'payment']);

        return Inertia::render('dashboard/settings/payment', compact('payments'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function payment_update(GatewayRequest $request, string $id)
    {
        $this->settings->paymentUpdate($request->validated(), $id);

        return back()->with('success', 'Payment gateway settings updated successfully');
    }
}
