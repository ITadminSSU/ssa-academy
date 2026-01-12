<?php

namespace Modules\PaymentGateways\Http\Controllers\Payout;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\PaymentGateways\Services\PayoutService;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class StripeController extends Controller
{
    public function __construct(
        private PayoutService $payoutService
    ) {}

    // Stripe payment 
    public function payment(Request $request)
    {
        $payout = $this->payoutService->getPayoutRequest($request->request_id);
        $stripe = $this->payoutService->getPayoutGateway($payout->user->instructor_id, 'stripe');
        $stripeSecret = $stripe['fields']['test_mode'] ? $stripe['fields']['test_secret_key'] : $stripe['fields']['live_secret_key'];

        Stripe::setApiKey($stripeSecret);
        $response = Session::create([
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => strtolower($stripe['fields']['currency']),
                        'product_data' => [
                            'name' => 'Course Purchase',
                        ],
                        'unit_amount' => number_format($payout->amount, 2, '.', '') * 100,
                    ],
                    'quantity' => 1,
                ]
            ],
            'mode' => 'payment',
            'success_url' => route('payouts.stripe.success'),
            'cancel_url' => route('payouts.request.index'),
        ]);

        $user = Auth::user();
        setTempStore([
            'user_id' => $user->id,
            'properties' => [
                'slug' => $request->slug,
                'request_id' => $request->request_id,
                'stripe_id' => $response->id,
            ]
        ]);

        return redirect()->away($response->url);
    }


    // Stripe payment success
    public function success(Request $request)
    {
        $user = Auth::user();
        $temp = getTempStore($user->id);

        $slug = $temp->properties['slug'];
        $stripe_id = $temp->properties['stripe_id'];
        $request_id = $temp->properties['request_id'];

        $payout = $this->payoutService->getPayoutRequest($request_id);
        $stripe = $this->payoutService->getPayoutGateway($payout->user->instructor_id, 'stripe');
        $stripeSecret = $stripe['fields']['test_mode'] ? $stripe['fields']['test_secret_key'] : $stripe['fields']['live_secret_key'];

        try {
            Stripe::setApiKey($stripeSecret);
            $order = Session::retrieve($stripe_id);

            $this->payoutService->completePayoutRequest($request_id, $order->payment_intent, 'stripe');

            if ($slug == 'api') {
                return redirect()->to(env('FRONTEND_URL') . '/student');
            } else {
                return redirect()
                    ->route('payouts.request.index')
                    ->with('success', 'Congratulation! payout have completed');
            }
        } catch (\Throwable $th) {
            return redirect()
                ->route('payouts.request.index')
                ->with('error', $th->getMessage());
        }
    }


    // Stripe payment cancel
    public function cancel()
    {
        return redirect()
            ->route('payouts.request.index')
            ->with('error', 'Your payment have failed, please try again later.');
    }
}
