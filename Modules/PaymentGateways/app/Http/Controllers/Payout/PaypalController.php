<?php

namespace Modules\PaymentGateways\Http\Controllers\Payout;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\PaymentGateways\Services\PayoutService;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PaypalController extends Controller
{
    public function __construct(
        private PayoutService $payoutService
    ) {}

    // Paypal payment 
    public function payment(Request $request)
    {
        $payout = $this->payoutService->getPayoutRequest($request->request_id);
        $paypal = $this->payoutService->getPayoutGateway($payout->user->instructor_id, 'paypal');
        $paypalMode = $paypal['fields']['test_mode'] ? 'sandbox' : 'live';

        $config = setPaypalConfig($paypal['fields'], $paypalMode);

        $provider = new PayPalClient;
        $provider->setApiCredentials($config);
        $accessToken = $provider->getAccessToken();

        $response = $provider->createOrder([
            "intent" => "CAPTURE",
            "application_context" => [
                "return_url" => route('payouts.paypal.success'),
                "cancel_url" => route('payouts.request.index'),
            ],
            "purchase_units" => [
                [
                    "amount" => [
                        "currency_code" => strtolower($paypal['fields']['currency']),
                        "value" => number_format($payout->amount, 2, '.', '')
                    ]
                ]
            ]
        ]);

        if (isset($response['id']) && $response['id'] != null) {
            $user = Auth::user();
            setTempStore([
                'user_id' => $user->id,
                'properties' => [
                    'slug' => $request->slug,
                    'request_id' => $request->request_id,
                    'paypal_order_id' => $response['id'],
                ]
            ]);

            foreach ($response['links'] as $link) {
                if ($link['rel'] == 'approve') {
                    return redirect()->away($link['href']);
                }
            }
        } else {
            return redirect()
                ->route('payouts.request.index')
                ->with('error', 'Your payment have failed, please try again later.');
        }
    }

    // Paypal payment success
    public function success(Request $request)
    {
        $user = Auth::user();
        $temp = getTempStore($user->id);

        $slug = $temp->properties['slug'];
        $request_id = $temp->properties['request_id'];

        $payout = $this->payoutService->getPayoutRequest($request_id);
        $paypal = $this->payoutService->getPayoutGateway($payout->user->instructor_id, 'paypal');
        $paypalMode = $paypal['fields']['test_mode'] ? 'sandbox' : 'live';

        try {
            $config = setPaypalConfig($paypal['fields'], $paypalMode);

            $provider = new PayPalClient;
            $provider->setApiCredentials($config);
            $accessToken = $provider->getAccessToken();
            $response = $provider->capturePaymentOrder($request->token);

            if (isset($response['status']) && $response['status'] == 'COMPLETED') {
                $this->payoutService->completePayoutRequest($request_id, $response['id'], 'paypal');

                if ($slug == 'api') {
                    return redirect()->to(env('FRONTEND_URL') . '/student');
                } else {
                    return redirect()
                        ->route('payouts.request.index')
                        ->with('success', 'Congratulation! payout have completed');
                }
            } else {
                return redirect()
                    ->route('payouts.request.index')
                    ->with('error', 'Your payment have failed, please try again later.');
            }
        } catch (\Throwable $th) {
            return redirect()
                ->route('payouts.request.index')
                ->with('error', $th->getMessage());
        }
    }

    // Paypal payment cancel
    public function cancel()
    {
        return redirect()
            ->route('payouts.request.index')
            ->with('error', 'Your payment have failed, please try again later.');
    }
}
