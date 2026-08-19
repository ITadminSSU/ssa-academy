<?php

namespace Modules\PaymentGateways\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Services\Course\CourseCouponService;
use App\Services\Payment\ExternalCheckoutService;
use App\Services\Payment\LaunchOfferEnrollmentService;
use App\Services\Payment\LaunchOfferService;
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
        private LaunchOfferService $launchOffer,
        private LaunchOfferEnrollmentService $launchOfferEnrollment,
        private CourseCouponService $courseCoupon,
    ) {}

    public function index(Request $request, string $from, string $item_type, string $id)
    {
        $user = Auth::user();
        $launchOfferPayload = null;
        $checkoutMode = null;

        if ($item_type === 'course') {
            $course = Course::find($id);

            if ($course) {
                if (! $this->externalCheckout->userCanAccessCheckoutCourse($user, $course)) {
                    $message = $course->isComingSoon()
                        ? ($course->launch_at
                            ? 'This course launches on '.$course->launch_at->timezone(config('app.timezone'))->format('M j, Y').'.'
                            : 'This course is coming soon.')
                        : 'This course is only available to internal employees.';

                    return redirect()
                        ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                        ->with('error', $message);
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

                if (! $this->externalCheckout->requiresPaidCheckout($user, $course)) {
                    return redirect()
                        ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                        ->with('info', 'This course does not require payment. Enroll from the course page.');
                }

                $enrollment = CourseEnrollment::query()
                    ->where('user_id', $user->id)
                    ->where('course_id', $course->id)
                    ->first();

                $launchOfferPayload = $this->launchOffer->toFrontendPayload($course, $enrollment);
                $checkoutMode = $this->launchOfferEnrollment->resolveCheckoutMode($user, $course);
            }
        }

        $payments = $this->externalCheckout->filterGatewaysForCheckout(
            $this->settings->getSettings(['type' => 'payment']),
            $user
        );

        if (! $this->externalCheckout->hasActiveGateway($payments)) {
            return redirect()
                ->route('category.courses', ['category' => 'all'])
                ->with('error', 'Online payment is not available yet. Please contact support.');
        }

        $currency = app('system_settings')->fields['selling_currency'] ?? 'USD';
        $checkoutItem = $this->payment->getCheckoutItem($item_type, $id, $request->coupon);

        if ($checkoutMode === 'deposit' && $launchOfferPayload) {
            $amount = (float) $launchOfferPayload['deposit_amount'];
            $checkoutItem = [
                ...$checkoutItem,
                'subtotal' => $amount,
                'taxAmount' => 0,
                'couponDiscount' => 0,
                'discountedPrice' => $amount,
                'finalPrice' => $amount,
                'coupon' => null,
            ];
        } elseif ($checkoutMode === 'balance' && $launchOfferPayload) {
            $amount = (float) $launchOfferPayload['balance_amount'];
            $coupon = $request->coupon
                ? $this->courseCoupon->getCourseValidCoupon($id, $request->coupon, $user->id)
                : null;
            $checkoutItem = [
                ...$checkoutItem,
                ...$this->payment->calculateCustomPrice($amount, $coupon),
                'coupon' => $coupon,
            ];
        } elseif ($checkoutMode === 'full_launch' && $launchOfferPayload) {
            $amount = (float) $launchOfferPayload['full_upfront_price'];
            $coupon = $request->coupon
                ? $this->courseCoupon->getCourseValidCoupon($id, $request->coupon, $user->id)
                : null;
            $checkoutItem = [
                ...$checkoutItem,
                ...$this->payment->calculateCustomPrice($amount, $coupon),
                'coupon' => $coupon,
            ];
        } elseif ($checkoutMode === 'upfront_subscription' && isset($course)) {
            $amount = (float) ($course->price ?? 0);
            $checkoutItem = [
                ...$checkoutItem,
                'subtotal' => $amount,
                'taxAmount' => 0,
                'couponDiscount' => 0,
                'discountedPrice' => $amount,
                'finalPrice' => $amount,
                'coupon' => null,
            ];
        }

        return view('paymentgateways::payment', [
            'id' => $id,
            'from' => $from,
            'coupon' => $request->coupon,
            'item_type' => $item_type,
            'payments' => $payments,
            'currency' => $currency,
            'launchOffer' => $launchOfferPayload,
            'checkoutMode' => $checkoutMode,
            ...$checkoutItem,
        ]);
    }

    public function payment(Request $request)
    {
        $payments = $this->settings->getSettings(['type' => 'payment']);

        return Inertia::render('dashboard/settings/payment', compact('payments'));
    }

    public function payment_update(GatewayRequest $request, string $id)
    {
        $this->settings->paymentUpdate($request->validated(), $id);

        return back()->with('success', 'Payment gateway settings updated successfully');
    }
}
