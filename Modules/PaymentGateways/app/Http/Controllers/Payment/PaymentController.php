<?php

namespace Modules\PaymentGateways\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Course\Course;
use App\Services\Payment\ExternalCheckoutService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\PaymentGateways\Http\Requests\GatewayRequest;
use Modules\PaymentGateways\Services\PaymentService;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $payment,
        private SettingsService $settings,
        private ExternalCheckoutService $externalCheckout,
    ) {}

    public function index(Request $request, string $from, string $item_type, string $id)
    {
        $user = Auth::user();

        if ($item_type === 'course') {
            $course = Course::find($id);

            if ($course) {
                if (!$this->externalCheckout->userCanAccessCheckoutCourse($user, $course)) {
                    return redirect()
                        ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                        ->with('error', 'This course is only available to internal employees.');
                }

                if ($user->qualifiesForFreeCourseAccess()) {
                    return redirect()
                        ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                        ->with('info', 'Employee learners can enroll in this course for free from the course page.');
                }

                if ($this->externalCheckout->isAlreadyEnrolled($user, $course)) {
                    return redirect()
                        ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                        ->with('info', 'You are already enrolled in this course.');
                }

                if (!$this->externalCheckout->requiresPaidCheckout($user, $course)) {
                    return redirect()
                        ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                        ->with('info', 'This course does not require payment. Enroll from the course page.');
                }
            }
        }

        $payments = $this->externalCheckout->filterGatewaysForCheckout(
            $this->settings->getSettings(['type' => 'payment']),
            $user
        );

        if (!$this->externalCheckout->hasActiveGateway($payments)) {
            return redirect()
                ->route('category.courses', ['category' => 'all'])
                ->with('error', 'Online payment is not available yet. Please contact support.');
        }

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
