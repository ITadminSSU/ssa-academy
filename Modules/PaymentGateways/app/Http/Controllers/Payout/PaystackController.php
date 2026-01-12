<?php

namespace Modules\PaymentGateways\Http\Controllers\Payout;

use App\Http\Controllers\Controller;
use Modules\PaymentGateways\Services\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class PaystackController extends Controller
{
    public function __construct(
        private PayoutService $payoutService
    ) {}

    public function paystack_redirect(Request $request)
    {
        $user = Auth::user();
        $payout = $this->payoutService->getPayoutRequest($request->request_id);
        $paystack = $this->payoutService->getPayoutGateway($payout->user->instructor_id, 'paystack');
        $paystackPublicKey = $paystack['fields']['test_mode'] ? $paystack['fields']['test_public_key'] : $paystack['fields']['live_public_key'];

        setTempStore([
            'user_id' => $user->id,
            'properties' => [
                'slug' => $request->slug,
                'request_id' => $request->request_id,
            ]
        ]);

        return view('paymentgateways::gateways.paystack', [
            'user' => $payout->user,
            'price' => $payout->amount,
            'currency' => $paystack['fields']['currency'],
            'paystackPublicKey' => $paystackPublicKey,
            'action' => '/payouts/paystack/callback',
        ]);
    }


    public function verify_transaction(Request $request)
    {
        $user = Auth::user();
        $temp = getTempStore($user->id);

        $slug = $temp->properties['slug'];
        $request_id = $temp->properties['request_id'];

        $payout = $this->payoutService->getPayoutRequest($request_id);
        $paystack = $this->payoutService->getPayoutGateway($payout->user->instructor_id, 'paystack');
        $paystackSecretKey = $paystack['fields']['test_mode'] ? $paystack['fields']['test_secret_key'] : $paystack['fields']['live_secret_key'];

        try {
            $reference = $request->reference;
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $paystackSecretKey
            ])->get("https://api.paystack.co/transaction/verify/$reference");

            $payment = json_decode($response);

            if ($payment->status == true) {
                // Complete payout with original USD amount for system consistency
                $this->payoutService->completePayoutRequest($request_id, $payment->data->id, 'paystack');

                if ($slug == 'api') {
                    return redirect()->to(env('FRONTEND_URL') . '/student');
                } else {
                    return redirect()->route('payouts.request.index')->with('success', 'Congratulation! payout have completed');
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
}
