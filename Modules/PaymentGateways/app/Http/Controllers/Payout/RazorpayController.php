<?php

namespace Modules\PaymentGateways\Http\Controllers\Payout;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\PaymentGateways\Services\PayoutService;
use Razorpay\Api\Api;

class RazorpayController extends Controller
{
   public function __construct(
      private PayoutService $payoutService
   ) {}

   // Razorpay payment redirect
   public function index(Request $request)
   {
      $user = Auth::user();
      $payout = $this->payoutService->getPayoutRequest($request->request_id);
      $razorpay = $this->payoutService->getPayoutGateway($payout->user->instructor_id, 'razorpay');

      setTempStore([
         'user_id' => $user->id,
         'properties' => [
            'slug' => $request->slug,
            'request_id' => $request->request_id,
         ]
      ]);

      $data = [
         'user' => $payout->user,
         'key' => $razorpay['fields']['api_key'],
         'amount' => $payout->amount,
         'currency' => $razorpay['fields']['currency'],
         'action' => route('payouts.razorpay.payment'),
         'name' => "instructor-payout",
         'title' => "Payout Details",
         'description' => "Payout Request #" . $request->request_id,
      ];

      return view('paymentgateways::gateways.razorpay', $data);
   }

   // Razorpay payment callback
   public function payment(Request $request)
   {
      $user = Auth::user();
      $temp = getTempStore($user->id);

      $slug = $temp->properties['slug'];
      $request_id = $temp->properties['request_id'];

      $payout = $this->payoutService->getPayoutRequest($request_id);
      $razorpay = $this->payoutService->getPayoutGateway($payout->user->instructor_id, 'razorpay');
      // dd($razorpay);
      $key = $razorpay['fields']['api_key'];
      $secret = $razorpay['fields']['api_secret'];

      $api = new Api($key, $secret);

      if ($request->has('razorpay_payment_id') && $request->filled('razorpay_payment_id')) {
         try {
            $payment = $api->payment->fetch($request->razorpay_payment_id);
            $response = $api->payment->fetch($request->razorpay_payment_id)->capture(['amount' => $payment['amount']]);
            $transactionId = $response['id'];

            $this->payoutService->completePayoutRequest($request_id, $transactionId, 'razorpay');

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

      return redirect()
         ->route('payouts.request.index')
         ->with('error', 'Transaction was failed.');
   }
}
