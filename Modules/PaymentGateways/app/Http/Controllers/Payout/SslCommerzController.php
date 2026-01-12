<?php

namespace Modules\PaymentGateways\Http\Controllers\Payout;

use App\Models\User;
use App\Models\TempStore;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\PaymentGateways\Services\PayoutService;
use Modules\PaymentGateways\Services\SslCommerz\SslCommerzNotification;

class SslCommerzController extends Controller
{
   public function __construct(
      private PayoutService $payoutService
   ) {}

   // SSLCommerz payment
   public function index(Request $request)
   {
      $request->validate(['phone' => 'required']);

      $user = Auth::user();
      $payout = $this->payoutService->getPayoutRequest($request->request_id);
      $sslcommerz = $this->payoutService->getPayoutGateway($payout->user->instructor_id, 'sslcommerz');

      $post_data = array();
      $post_data['total_amount'] = round($payout->amount, 2);
      $post_data['currency'] = $sslcommerz['fields']['currency'];
      $post_data['tran_id'] = uniqid(); // tran_id must be unique

      # CUSTOMER INFORMATION
      $post_data['cus_name'] = $user->name;
      $post_data['cus_email'] = $user->email;
      $post_data['cus_phone'] = $request->phone;

      $post_data['shipping_method'] = "NO";
      $post_data['product_name'] = "Payout Request #" . $request->request_id;
      $post_data['product_category'] = "Instructor Payout";
      $post_data['product_profile'] = "instructor-payout";

      $sslc = new SslCommerzNotification();

      setTempStore([
         'key' => $post_data['tran_id'],
         'user_id' => $user->id,
         'properties' => [
            'slug' => $request->slug,
            'request_id' => $request->request_id,
         ]
      ]);

      $payment_options = $sslc->makePayment($post_data, 'hosted');

      if (!is_array($payment_options)) {
         print_r($payment_options);
         $payment_options = array();
      }
   }

   // SSLCommerz payment success
   public function success(Request $request)
   {
      $tran_id = $request->input('tran_id');
      $amount = $request->input('amount');
      $currency = $request->input('currency');

      $sslc = new SslCommerzNotification();
      $validation = $sslc->orderValidate($request->all(), $tran_id, $amount, $currency);
      $temp = TempStore::where('key', $tran_id)->first();

      if (!$temp) {
         return redirect()
            ->route('payouts.request.index')
            ->with('error', 'Transaction data not found.');
      }

      $user_id = $temp->user_id;
      $slug = $temp->properties['slug'];
      $request_id = $temp->properties['request_id'];

      if ($validation) {
         // Login user before processing payment
         Auth::login(User::find($user_id));

         $this->payoutService->completePayoutRequest($request_id, $tran_id, 'sslcommerz');

         $temp->delete();

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
            ->with('error', 'Transaction was failed.');
      }
   }

   // SSLCommerz payment fail
   public function fail(Request $request)
   {
      $tran_id = $request->input('tran_id');

      $temp = TempStore::where('key', $tran_id)->first();
      if ($temp) {
         Auth::login(User::find($temp->user_id));
         $temp->delete();
      }

      return redirect()
         ->route('payouts.request.index')
         ->with('error', 'Payment failed. Please try again or contact support if the problem persists.');
   }

   // SSLCommerz payment cancel
   public function cancel(Request $request)
   {
      $tran_id = $request->input('tran_id');

      $temp = TempStore::where('key', $tran_id)->first();
      if ($temp) {
         Auth::login(User::find($temp->user_id));
         $temp->delete();
      }

      return redirect()
         ->route('payouts.request.index')
         ->with('error', 'Payment was cancelled. You can try again anytime.');
   }

   // SSLCommerz IPN (Instant Payment Notification)
   public function ipn(Request $request)
   {
      if ($request->input('tran_id')) {
         $tran_id = $request->input('tran_id');

         $temp = TempStore::where('key', $tran_id)->first();
         if ($temp) {
            Auth::login(User::find($temp->user_id));
            $temp->delete();
         }

         return redirect()
            ->route('payouts.request.index')
            ->with('error', 'Payment processing error. Please try again or contact support.');
      } else {
         return redirect()
            ->route('payouts.request.index')
            ->with('error', 'Invalid payment data received. Please try again.');
      }
   }
}
