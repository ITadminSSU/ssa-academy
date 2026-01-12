<?php

namespace Modules\PaymentGateways\Http\Controllers\Payment;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\MediaService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Auth;
use Modules\PaymentGateways\Services\PaymentService;
use Modules\PaymentGateways\Models\PaymentHistory;
use Illuminate\Support\Str;
use Inertia\Inertia;

class OfflineController extends Controller
{
    private $offline;

    public function __construct(
        private MediaService $media,
        private PaymentService $payment,
        private SettingsService $settingsService,
    ) {
        $this->offline = $this->settingsService->getSetting(['type' => 'payment', 'sub_type' => 'offline']);
    }

    /**
     * Show offline payment instructions page
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $checkoutItem = $this->payment->getCheckoutItem(
            $request->item_type,
            $request->item_id,
            $request->coupon
        );

        // Store checkout data in temporary storage
        setTempStore([
            'user_id' => $user->id,
            'properties' => [
                'from' => $request->from,
                'item_type' => $request->item_type,
                'item_id' => $request->item_id,
                'tax_amount' => $checkoutItem['taxAmount'],
                'final_price' => $checkoutItem['finalPrice'],
                'coupon_code' => $checkoutItem['coupon'] ? $checkoutItem['coupon']->code : null,
            ]
        ]);

        return Inertia::render('offline-payment/index', [
            'user' => $user,
            'item' => $checkoutItem['item'],
            'amount' => $checkoutItem['finalPrice'],
            'currency' => app('system_settings')->fields['selling_currency'] ?? 'USD',
            'payment_instructions' => $this->offline->fields['payment_instructions'] ?? '',
            'payment_details' => $this->offline->fields['payment_details'] ?? '',
        ]);
    }

    /**
     * Submit offline payment proof
     */
    public function submit(Request $request)
    {
        $request->validate([
            'payment_info' => 'required|string|max:100',
            'payment_date' => 'required|date',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:1024',
        ]);

        $user = Auth::user();
        $temp = getTempStore($user->id);

        if (!$temp) {
            return redirect()
                ->route('student.index', ['tab' => 'courses'])
                ->with('error', 'Session expired. Please try again.');
        }

        $from = $temp->properties['from'];
        $item_type = $temp->properties['item_type'];
        $item_id = $temp->properties['item_id'];
        $tax_amount = $temp->properties['tax_amount'];
        $final_price = $temp->properties['final_price'];
        $coupon_code = $temp->properties['coupon_code'];

        if (!in_array($item_type, ['course', 'exam'])) {
            return redirect()->route('student.index', ['tab' => 'courses'])
                ->with('error', 'Invalid item type');
        }

        try {
            // Generate unique transaction ID for offline payment
            $transactionId = 'OFFLINE-' . strtoupper(Str::random(12));

            // Create payment history with pending status
            $this->payment->coursesBuy(
                'offline',
                $item_type,
                $item_id,
                $transactionId,
                $tax_amount,
                $final_price,
                $coupon_code
            );

            // Update the payment history with offline payment details
            $paymentHistory = PaymentHistory::where('transaction_id', $transactionId)->first();

            if ($paymentHistory) {
                $paymentHistory->update([
                    'meta' => [
                        'status' => 'pending',
                        'payment_info' => $request->payment_info,
                        'payment_date' => $request->payment_date,
                        'attachment' => $request->attachment ? $this->media->addNewDeletePrev($paymentHistory, $request->attachment, 'payment_attachment') : null,
                        'submitted_at' => now()->toDateTimeString(),
                    ]
                ]);
            }

            if ($from == 'api') {
                return redirect()->to(env('FRONTEND_URL') . '/student?payment=pending');
            } else {
                return redirect()
                    ->route('student.index', ['tab' => 'courses'])
                    ->with('success', 'Your payment details have been submitted successfully. Your enrollment will be activated once the payment is verified by our team.');
            }
        } catch (\Throwable $th) {
            return redirect()
                ->route('payments.index', ['from' => $from, 'item' => $item_type, 'id' => $item_id])
                ->with('error', $th->getMessage());
        }
    }

    /**
     * Cancel offline payment
     */
    public function cancel()
    {
        $user = Auth::user();
        $temp = getTempStore($user->id);

        if (!$temp) {
            return redirect()->route('student.index', ['tab' => 'courses']);
        }

        $from = $temp->properties['from'];
        $item_type = $temp->properties['item_type'];
        $item_id = $temp->properties['item_id'];

        return redirect()
            ->route('payments.index', ['from' => $from, 'item' => $item_type, 'id' => $item_id])
            ->with('error', 'Payment cancelled. Please try again.');
    }
}
