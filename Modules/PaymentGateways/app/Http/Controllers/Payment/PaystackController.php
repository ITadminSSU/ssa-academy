<?php

namespace Modules\PaymentGateways\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Modules\PaymentGateways\Services\PaymentService;

class PaystackController extends Controller
{
    private $paystack;
    private $paystackPublicKey;
    private $paystackSecretKey;

    public function __construct(
        private PaymentService $payment,
        private SettingsService $settingsService,
    ) {
        $this->paystack = $this->settingsService->getSetting(['type' => 'payment', 'sub_type' => 'paystack']);
        $this->paystackPublicKey = $this->paystack->fields['test_mode'] ? $this->paystack->fields['test_public_key'] : $this->paystack->fields['live_public_key'];
        $this->paystackSecretKey = $this->paystack->fields['test_mode'] ? $this->paystack->fields['test_secret_key'] : $this->paystack->fields['live_secret_key'];
    }

    // Paystack payment redirect
    public function paystack_redirect(Request $request)
    {
        $user = Auth::user();
        $checkoutItem = $this->payment->getCheckoutItem(
            $request->item_type,
            $request->item_id,
            $request->coupon
        );

        setTempStore([
            'user_id' => $user->id,
            'properties' => [
                'from' => $request->from,
                'item_type' => $request->item_type,
                'item_id' => $request->item_id,
                'tax_amount' => $checkoutItem['taxAmount'],
                'coupon_code' => $checkoutItem['coupon'] ? $checkoutItem['coupon']->code : null,
                'original_price' => $checkoutItem['finalPrice'],
            ]
        ]);

        return view('paymentgateways::gateways.paystack', [
            'user' => $user,
            'price' => $checkoutItem['finalPrice'],
            'currency' => $this->paystack->fields['currency'],
            'paystackPublicKey' => $this->paystackPublicKey,
            'action' => '/payments/paystack/callback',
        ]);
    }

    // Paystack payment callback/verification
    public function verify_transaction(Request $request)
    {
        $user = Auth::user();
        $temp = getTempStore($user->id);

        $from = $temp->properties['from'];
        $item_type = $temp->properties['item_type'];
        $item_id = $temp->properties['item_id'];
        $tax_amount = $temp->properties['tax_amount'];
        $coupon_code = $temp->properties['coupon_code'];
        $original_price = $temp->properties['original_price'];

        try {
            $reference = $request->reference;
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->paystackSecretKey
            ])->get("https://api.paystack.co/transaction/verify/$reference");

            $payment = json_decode($response);

            if ($payment->status == true) {
                // Use original price for consistency in the system
                $this->payment->coursesBuy(
                    'paystack',
                    $item_type,
                    $item_id,
                    $payment->data->id,
                    $tax_amount,
                    $original_price,
                    $coupon_code
                );

                if ($from == 'api') {
                    return redirect()->to(env('FRONTEND_URL') . '/student');
                } else {
                    return redirect()
                        ->route('student.index', ['tab' => 'courses'])
                        ->with('success', 'Congratulation! Your payment have completed');
                }
            } else {
                return redirect()
                    ->route('payments.index', ['from' => $from, 'item' => $item_type, 'id' => $item_id])
                    ->with('error', 'Your payment have failed, please try again later.');
            }
        } catch (\Throwable $th) {
            return redirect()
                ->route('payments.index', ['from' => $from, 'item' => $item_type, 'id' => $item_id])
                ->with('error', $th->getMessage());
        }
    }
}
