<?php

namespace Modules\PaymentGateways\Http\Controllers\Payment;

use App\Enums\CourseBillingModel;
use App\Models\Course\Course;
use App\Http\Controllers\Controller;
use App\Services\Payment\ExternalCheckoutService;
use App\Services\Payment\StripeCustomerService;
use App\Services\Payment\SubscriptionService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\PaymentGateways\Services\PaymentService;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class StripeController extends Controller
{
    private $stripe;
    private $stripeSecret;

    public function __construct(
        private PaymentService $payment,
        private SettingsService $settingsService,
        private ExternalCheckoutService $externalCheckout,
        private StripeCustomerService $stripeCustomer,
        private SubscriptionService $subscriptionService,
    ) {
        $this->stripe = $this->settingsService->getSetting(['type' => 'payment', 'sub_type' => 'stripe']);
        $this->stripeSecret = $this->stripe->fields['test_mode'] ? $this->stripe->fields['test_secret_key'] : $this->stripe->fields['live_secret_key'];
    }

    public function payment(Request $request)
    {
        $user = Auth::user();

        if (!($this->stripe->fields['active'] ?? false)) {
            return redirect()
                ->route('payments.index', [
                    'from' => $request->from,
                    'item' => $request->item_type,
                    'id' => $request->item_id,
                ])
                ->with('error', 'Stripe is not enabled. Please contact support.');
        }

        $course = null;

        if ($request->item_type === 'course') {
            $course = Course::find($request->item_id);

            if (!$course) {
                return redirect()->route('category.courses', ['category' => 'all'])
                    ->with('error', 'Course not found.');
            }

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

            if (!$this->externalCheckout->canPurchaseCourse($user, $course)) {
                if ($this->externalCheckout->hasActiveCourseAccess($user, $course)) {
                    return redirect()
                        ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                        ->with('info', 'You already have active access to this course.');
                }

                return redirect()
                    ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                    ->with('info', 'This course does not require payment.');
            }

            if ($course->usesSubscriptionBilling()) {
                return $this->startSubscriptionCheckout($request, $user, $course);
            }
        }

        $checkoutItem = $this->payment->getCheckoutItem(
            $request->item_type,
            $request->item_id,
            $request->coupon
        );

        Stripe::setApiKey($this->stripeSecret);
        $response = Session::create([
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => strtolower($this->stripe->fields['currency']),
                        'product_data' => [
                            'name' => ucfirst($request->item_type) . ' Purchase',
                        ],
                        'unit_amount' => round($checkoutItem['finalPrice'] * 100),
                    ],
                    'quantity' => 1,
                ]
            ],
            'mode' => 'payment',
            'success_url' => route('payments.stripe.success'),
            'cancel_url' => route('payments.stripe.cancel'),
            'client_reference_id' => (string) $user->id,
            'metadata' => [
                'user_id' => (string) $user->id,
                'item_type' => $request->item_type,
                'item_id' => (string) $request->item_id,
                'billing_model' => CourseBillingModel::ONE_TIME->value,
            ],
        ]);

        setTempStore([
            'user_id' => $user->id,
            'properties' => [
                'from' => $request->from,
                'item_type' => $request->item_type,
                'item_id' => $request->item_id,
                'billing_model' => CourseBillingModel::ONE_TIME->value,
                'stripe_id' => $response->id,
                'tax_amount' => $checkoutItem['taxAmount'],
                'coupon_code' => $checkoutItem['coupon'] ? $checkoutItem['coupon']->code : null,
            ]
        ]);

        return redirect()->away($response->url);
    }

    protected function startSubscriptionCheckout(Request $request, $user, Course $course)
    {
        if (empty($course->stripe_price_id)) {
            return redirect()
                ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                ->with('error', 'This subscription course is not configured for checkout yet. Please contact support.');
        }

        $this->stripeCustomer->configureStripe();
        $customerId = $this->stripeCustomer->findOrCreateCustomer($user);

        Stripe::setApiKey($this->stripeSecret);

        $response = Session::create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items' => [
                [
                    'price' => $course->stripe_price_id,
                    'quantity' => 1,
                ],
            ],
            'success_url' => route('payments.stripe.success'),
            'cancel_url' => route('payments.stripe.cancel'),
            'client_reference_id' => (string) $user->id,
            'metadata' => [
                'user_id' => (string) $user->id,
                'item_type' => 'course',
                'item_id' => (string) $course->id,
                'billing_model' => CourseBillingModel::SUBSCRIPTION->value,
            ],
            'subscription_data' => [
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'course_id' => (string) $course->id,
                ],
            ],
        ]);

        setTempStore([
            'user_id' => $user->id,
            'properties' => [
                'from' => $request->from,
                'item_type' => 'course',
                'item_id' => $course->id,
                'billing_model' => CourseBillingModel::SUBSCRIPTION->value,
                'stripe_id' => $response->id,
                'tax_amount' => 0,
                'coupon_code' => null,
            ],
        ]);

        return redirect()->away($response->url);
    }

    public function success(Request $request)
    {
        $user = Auth::user();
        $temp = getTempStore($user->id);

        if (!$temp || empty($temp->properties['stripe_id'])) {
            return redirect()
                ->route('category.courses', ['category' => 'all'])
                ->with('error', 'Payment session expired. Please try again.');
        }

        $from = $temp->properties['from'];
        $item_type = $temp->properties['item_type'];
        $item_id = $temp->properties['item_id'];
        $stripe_id = $temp->properties['stripe_id'];
        $tax_amount = $temp->properties['tax_amount'];
        $coupon_code = $temp->properties['coupon_code'];
        $billing_model = $temp->properties['billing_model'] ?? CourseBillingModel::ONE_TIME->value;

        if (!in_array($item_type, ['course', 'exam'], true)) {
            return redirect()->route('student.index', ['tab' => 'courses'])
                ->with('error', 'Invalid item type');
        }

        try {
            Stripe::setApiKey($this->stripeSecret);
            $order = Session::retrieve($stripe_id);

            if ($billing_model === CourseBillingModel::SUBSCRIPTION->value || $order->mode === 'subscription') {
                $this->subscriptionService->activateFromCheckoutSession($order);

                if ($from == 'api') {
                    return redirect()->to(env('FRONTEND_URL') . '/student');
                }

                if ($course = Course::find($item_id)) {
                    return redirect()
                        ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                        ->with('success', 'Subscription active. You now have access to this course.');
                }
            }

            if ($order->payment_status !== 'paid') {
                return redirect()
                    ->route('payments.index', ['from' => $from, 'item' => $item_type, 'id' => $item_id])
                    ->with('error', 'Payment was not completed. Please try again.');
            }

            if ($item_type === 'course') {
                $course = Course::find($item_id);

                if ($course && $this->externalCheckout->hasActiveCourseAccess($user, $course)) {
                    return redirect()
                        ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                        ->with('success', 'You are already enrolled in this course.');
                }
            }

            $this->payment->coursesBuy(
                'stripe',
                $item_type,
                $item_id,
                $order->payment_intent,
                $tax_amount,
                ($order->amount_total / 100),
                $coupon_code
            );

            if ($from == 'api') {
                return redirect()->to(env('FRONTEND_URL') . '/student');
            }

            if ($item_type === 'course' && $course = Course::find($item_id)) {
                return redirect()
                    ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                    ->with('success', 'Payment successful. You are now enrolled in this course.');
            }

            return redirect()
                ->route('student.index', ['tab' => 'courses'])
                ->with('success', 'Congratulation! Your payment have completed');
        } catch (\Throwable $th) {
            return redirect()
                ->route('payments.index', ['from' => $from, 'item' => $item_type, 'id' => $item_id])
                ->with('error', $th->getMessage());
        }
    }

    public function cancel()
    {
        $user = Auth::user();
        $temp = getTempStore($user->id);

        if (!$temp) {
            return redirect()
                ->route('category.courses', ['category' => 'all'])
                ->with('error', 'Payment was cancelled.');
        }

        $from = $temp->properties['from'];
        $item_type = $temp->properties['item_type'];
        $item_id = $temp->properties['item_id'];

        return redirect()
            ->route('payments.index', ['from' => $from, 'item' => $item_type, 'id' => $item_id])
            ->with('error', 'Your payment have failed, please try again later.');
    }
}
