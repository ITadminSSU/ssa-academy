<?php

namespace Modules\PaymentGateways\Http\Controllers\Payment;

use App\Enums\CourseBillingModel;
use App\Http\Controllers\Controller;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Services\Course\CourseCouponService;
use App\Services\Course\CourseEnrollmentWelcomeMailService;
use App\Services\Course\CourseSectionService;
use App\Services\Payment\ExternalCheckoutService;
use App\Services\Payment\LaunchOfferEnrollmentService;
use App\Services\Payment\LaunchOfferService;
use App\Services\Payment\StripeCustomerService;
use App\Services\Payment\SubscriptionService;
use App\Services\SettingsService;
use App\Support\PaymentVoucherCopy;
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
        private LaunchOfferService $launchOffer,
        private LaunchOfferEnrollmentService $launchOfferEnrollment,
        private CourseCouponService $courseCoupon,
    ) {
        $this->stripe = $this->settingsService->getSetting(['type' => 'payment', 'sub_type' => 'stripe']);
        $this->stripeSecret = $this->stripe->fields['test_mode']
            ? $this->stripe->fields['test_secret_key']
            : $this->stripe->fields['live_secret_key'];
    }

    public function payment(Request $request)
    {
        $user = Auth::user();

        if (! ($this->stripe->fields['active'] ?? false)) {
            return redirect()
                ->route('payments.index', [
                    'from' => $request->from,
                    'item' => $request->item_type,
                    'id' => $request->item_id,
                ])
                ->with('error', 'Stripe is not enabled. Please contact support.');
        }

        if ($request->item_type === 'course') {
            $course = Course::find($request->item_id);

            if (! $course) {
                return redirect()->route('category.courses', ['category' => 'all'])
                    ->with('error', 'Course not found.');
            }

            if (! $this->externalCheckout->userCanAccessCheckoutCourse($user, $course)) {
                return redirect()
                    ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                    ->with('error', 'Checkout is not available for this course right now.');
            }

            if ($user->qualifiesForFreeCourseAccess()) {
                return redirect()
                    ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                    ->with('info', 'Employee learners can enroll in this course for free from the course page.');
            }

            if (! $this->externalCheckout->canPurchaseCourse($user, $course)) {
                if ($this->externalCheckout->hasActiveCourseAccess($user, $course)) {
                    return redirect()
                        ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                        ->with('info', 'You already have active access to this course.');
                }

                return redirect()
                    ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                    ->with('info', 'This course does not require payment.');
            }

            $mode = $this->launchOfferEnrollment->resolveCheckoutMode($user, $course);

            return match ($mode) {
                'deposit' => $this->startFixedAmountCheckout($request, $user, $course, 'deposit', $this->launchOffer->depositAmount($course)),
                'balance' => $this->startBalanceWithSubscriptionCheckout($request, $user, $course),
                'full_launch' => $this->startFullLaunchCheckout($request, $user, $course),
                'upfront_subscription' => $this->startUpfrontSubscriptionCheckout($request, $user, $course),
                'legacy_subscription' => $this->startSubscriptionCheckout($request, $user, $course),
                default => $this->startOneTimeCheckout($request, $user),
            };
        }

        return $this->startOneTimeCheckout($request, $user);
    }

    protected function startOneTimeCheckout(Request $request, $user)
    {
        $checkoutItem = $this->payment->getCheckoutItem(
            $request->item_type,
            $request->item_id,
            $request->coupon
        );

        $itemName = ucfirst($request->item_type).' Purchase';
        if ($request->item_type === 'course') {
            $namedCourse = Course::find($request->item_id);
            if ($namedCourse) {
                $itemName = $namedCourse->title;
            }
        }

        Stripe::setApiKey($this->stripeSecret);
        $sessionPayload = [
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => strtolower($this->stripe->fields['currency']),
                        'product_data' => $this->stripeProductDataForVoucher(
                            $itemName,
                            $checkoutItem['coupon'] ?? null,
                            $checkoutItem,
                        ),
                        'unit_amount' => (int) round($checkoutItem['finalPrice'] * 100),
                    ],
                    'quantity' => 1,
                ],
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
                'launch_offer_mode' => 'legacy_one_time',
            ],
        ];

        if ($checkoutItem['coupon']?->code) {
            $sessionPayload['metadata']['coupon_code'] = $checkoutItem['coupon']->code;
        }

        $voucherDescription = $this->stripeVoucherDescription($checkoutItem['coupon'] ?? null, $checkoutItem);
        if ($voucherDescription) {
            $sessionPayload['payment_intent_data'] = [
                'description' => $voucherDescription,
            ];
        }

        $response = Session::create($sessionPayload);

        setTempStore([
            'user_id' => $user->id,
            'properties' => [
                'from' => $request->from,
                'item_type' => $request->item_type,
                'item_id' => $request->item_id,
                'billing_model' => CourseBillingModel::ONE_TIME->value,
                'launch_offer_mode' => 'legacy_one_time',
                'stripe_id' => $response->id,
                'tax_amount' => $checkoutItem['taxAmount'],
                'coupon_code' => $checkoutItem['coupon'] ? $checkoutItem['coupon']->code : null,
            ],
        ]);

        return redirect()->away($response->url);
    }

    protected function startFixedAmountCheckout(Request $request, $user, Course $course, string $offerMode, float $amount)
    {
        $label = $offerMode === 'deposit'
            ? 'Pre-register deposit — '.$course->title
            : 'Course balance — '.$course->title;

        Stripe::setApiKey($this->stripeSecret);
        $response = Session::create([
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => strtolower($this->stripe->fields['currency']),
                        'product_data' => [
                            'name' => $label,
                        ],
                        'unit_amount' => (int) round($amount * 100),
                    ],
                    'quantity' => 1,
                ],
            ],
            'mode' => 'payment',
            'success_url' => route('payments.stripe.success'),
            'cancel_url' => route('payments.stripe.cancel'),
            'client_reference_id' => (string) $user->id,
            'metadata' => [
                'user_id' => (string) $user->id,
                'item_type' => 'course',
                'item_id' => (string) $course->id,
                'billing_model' => CourseBillingModel::ONE_TIME->value,
                'launch_offer_mode' => $offerMode,
            ],
        ]);

        setTempStore([
            'user_id' => $user->id,
            'properties' => [
                'from' => $request->from,
                'item_type' => 'course',
                'item_id' => $course->id,
                'billing_model' => CourseBillingModel::ONE_TIME->value,
                'launch_offer_mode' => $offerMode,
                'stripe_id' => $response->id,
                'tax_amount' => 0,
                'coupon_code' => null,
            ],
        ]);

        return redirect()->away($response->url);
    }

    protected function startBalanceWithSubscriptionCheckout(Request $request, $user, Course $course)
    {
        if (empty($course->stripe_price_id)) {
            return redirect()
                ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                ->with('error', 'Monthly subscription is not synced to Stripe yet. Please contact support.');
        }

        $balance = $this->launchOffer->balanceAmount($course);
        $trialEnd = $this->launchOffer->stripeTrialEndForBalanceCheckout($course);
        $coupon = $this->resolveSubmittedCoupon($course, $request->coupon, $user->id);
        $pricing = $this->payment->calculateCustomPrice($balance, $coupon);

        $this->stripeCustomer->configureStripe();
        $customerId = $this->stripeCustomer->findOrCreateCustomer($user);
        Stripe::setApiKey($this->stripeSecret);

        $subscriptionData = [
            'trial_end' => $trialEnd->timestamp,
            'metadata' => [
                'user_id' => (string) $user->id,
                'course_id' => (string) $course->id,
                'launch_offer_mode' => 'balance',
                'coupon_code' => $coupon?->code,
            ],
        ];
        $voucherDescription = $this->stripeVoucherDescription($coupon, $pricing);
        if ($voucherDescription) {
            $subscriptionData['description'] = $voucherDescription;
        }

        $response = Session::create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => strtolower($this->stripe->fields['currency']),
                        'product_data' => $this->stripeProductDataForVoucher(
                            'Launch balance — '.$course->title,
                            $coupon,
                            $pricing,
                        ),
                        'unit_amount' => (int) round($pricing['finalPrice'] * 100),
                    ],
                    'quantity' => 1,
                ],
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
                'launch_offer_mode' => 'balance',
                'coupon_code' => $coupon?->code,
                'coupon_discount' => (string) ($pricing['couponDiscount'] ?? 0),
                'charged_amount' => (string) ($pricing['finalPrice'] ?? 0),
            ],
            'subscription_data' => $subscriptionData,
        ]);

        setTempStore([
            'user_id' => $user->id,
            'properties' => [
                'from' => $request->from,
                'item_type' => 'course',
                'item_id' => $course->id,
                'billing_model' => CourseBillingModel::SUBSCRIPTION->value,
                'launch_offer_mode' => 'balance',
                'stripe_id' => $response->id,
                'tax_amount' => $pricing['taxAmount'],
                'coupon_code' => $coupon?->code,
                'coupon_discount' => (float) ($pricing['couponDiscount'] ?? 0),
                'charged_amount' => (float) ($pricing['finalPrice'] ?? 0),
            ],
        ]);

        return redirect()->away($response->url);
    }

    protected function startFullLaunchCheckout(Request $request, $user, Course $course)
    {
        return $this->startPaidEnrollmentThenMonthlyCheckout(
            $request,
            $user,
            $course,
            $this->launchOffer->fullUpfrontPrice($course),
            'full_launch',
            CourseBillingModel::SUBSCRIPTION->value,
        );
    }

    protected function startUpfrontSubscriptionCheckout(Request $request, $user, Course $course)
    {
        $upfront = (float) ($course->price ?? 0);

        if ($upfront < 1) {
            return redirect()
                ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                ->with('error', 'This course is missing an upfront enrollment price.');
        }

        return $this->startPaidEnrollmentThenMonthlyCheckout(
            $request,
            $user,
            $course,
            $upfront,
            'upfront_subscription',
            CourseBillingModel::UPFRONT_SUBSCRIPTION->value,
        );
    }

    /**
     * Charge course enrollment + first monthly subscription now (no free trial).
     * Pre-reg balance checkout stays separate and keeps the free-month trial.
     */
    protected function startPaidEnrollmentThenMonthlyCheckout(
        Request $request,
        $user,
        Course $course,
        float $upfront,
        string $offerMode,
        string $billingModel,
    ) {
        if (empty($course->stripe_price_id)) {
            return redirect()
                ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                ->with('error', 'Monthly subscription is not synced to Stripe yet. Please contact support.');
        }

        $coupon = $this->resolveSubmittedCoupon($course, $request->coupon, $user->id);
        $pricing = $this->payment->calculateCustomPrice($upfront, $coupon);
        $subscriptionPrice = $this->launchOffer->subscriptionPrice($course);

        $this->stripeCustomer->configureStripe();
        $customerId = $this->stripeCustomer->findOrCreateCustomer($user);
        Stripe::setApiKey($this->stripeSecret);

        $productData = $this->stripeProductDataForVoucher(
            'Course enrollment — '.$course->title,
            $coupon,
            $pricing,
        );
        // Keep enrollment line clean — voucher note only when a coupon is applied.

        $subscriptionData = [
            // No trial — first month is charged with enrollment today.
            'metadata' => [
                'user_id' => (string) $user->id,
                'course_id' => (string) $course->id,
                'launch_offer_mode' => $offerMode,
                'coupon_code' => (string) ($coupon?->code ?? ''),
            ],
        ];
        $voucherDescription = $this->stripeVoucherDescription($coupon, $pricing);
        if ($voucherDescription) {
            $subscriptionData['description'] = $voucherDescription;
        }

        $response = Session::create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => strtolower($this->stripe->fields['currency']),
                        'product_data' => $productData,
                        'unit_amount' => (int) round($pricing['finalPrice'] * 100),
                    ],
                    'quantity' => 1,
                ],
                [
                    'price_data' => [
                        'currency' => strtolower($this->stripe->fields['currency']),
                        'product_data' => [
                            'name' => 'Project Plans Subscription — '.$course->title,
                            'description' => 'Project Plan subscription payment',
                        ],
                        'unit_amount' => (int) round($subscriptionPrice * 100),
                        'recurring' => [
                            'interval' => 'month',
                        ],
                    ],
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
                'course_id' => (string) $course->id,
                'billing_model' => $billingModel,
                'launch_offer_mode' => $offerMode,
                'coupon_code' => $coupon?->code,
                'charged_amount' => (string) ($pricing['finalPrice'] ?? 0),
                'subscription_price' => (string) $subscriptionPrice,
            ],
            'subscription_data' => $subscriptionData,
        ]);

        setTempStore([
            'user_id' => $user->id,
            'properties' => [
                'from' => $request->from,
                'item_type' => 'course',
                'item_id' => $course->id,
                'billing_model' => $billingModel,
                'launch_offer_mode' => $offerMode,
                'stripe_id' => $response->id,
                'tax_amount' => $pricing['taxAmount'],
                'coupon_code' => $coupon?->code,
                'charged_amount' => (float) ($pricing['finalPrice'] ?? 0),
            ],
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
                'launch_offer_mode' => 'legacy_subscription',
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
                'launch_offer_mode' => 'legacy_subscription',
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

        if (! $temp || empty($temp->properties['stripe_id'])) {
            return redirect()
                ->route('category.courses', ['category' => 'all'])
                ->with('error', 'Payment session expired. Please try again.');
        }

        $from = $temp->properties['from'];
        $item_type = $temp->properties['item_type'];
        $item_id = $temp->properties['item_id'];
        $stripe_id = $temp->properties['stripe_id'];
        $tax_amount = $temp->properties['tax_amount'];
        $billing_model = $temp->properties['billing_model'] ?? CourseBillingModel::ONE_TIME->value;
        $offerMode = $temp->properties['launch_offer_mode'] ?? 'legacy_one_time';

        if (! in_array($item_type, ['course', 'exam'], true)) {
            return redirect()->route('student.index', ['tab' => 'courses'])
                ->with('error', 'Invalid item type');
        }

        try {
            Stripe::setApiKey($this->stripeSecret);
            $order = Session::retrieve($stripe_id);
            $coupon_code = $temp->properties['coupon_code']
                ?? data_get($order, 'metadata.coupon_code')
                ?? data_get($order, 'subscription_details.metadata.coupon_code')
                ?? null;
            $coupon_discount = (float) ($temp->properties['coupon_discount'] ?? 0);
            $charged_amount = (float) ($temp->properties['charged_amount'] ?? 0);
            $course = $item_type === 'course' ? Course::find($item_id) : null;

            if ($offerMode === 'deposit' && $course) {
                if ($order->payment_status !== 'paid') {
                    return redirect()
                        ->route('payments.index', ['from' => $from, 'item' => $item_type, 'id' => $item_id])
                        ->with('error', 'Payment was not completed. Please try again.');
                }

                $this->launchOfferEnrollment->recordDepositPayment(
                    $user,
                    $course,
                    'stripe',
                    (string) ($order->payment_intent ?: $order->id),
                    ($order->amount_total ?? 0) / 100,
                );

                $depositPaid = ($order->amount_total ?? 0) / 100;
                $depositAmount = $depositPaid > 0
                    ? $depositPaid
                    : $this->launchOffer->depositAmount($course);

                return redirect()
                    ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                    ->with(
                        'success',
                        'Seat reserved. Pay the remaining balance on launch day to unlock the full course. Your '.PaymentVoucherCopy::money($depositAmount).' deposit is non-refundable.'
                    );
            }

            if ($offerMode === 'balance' && $course) {
                $this->launchOfferEnrollment->recordBalancePayment(
                    $user,
                    $course,
                    'stripe',
                    (string) ($order->payment_intent ?: $order->subscription ?: $order->id),
                    $charged_amount > 0 ? $charged_amount : (($order->amount_total ?? 0) / 100),
                    $coupon_code,
                    $coupon_discount,
                );

                if ($order->mode === 'subscription' && ! empty($order->subscription)) {
                    $this->subscriptionService->activateFromCheckoutSession($order);
                }

                // Ensure watch history exists now that access is active.
                app(\App\Services\Course\CourseSectionService::class)
                    ->initWatchHistory((string) $course->id, 'lesson', (string) $user->id);

                return redirect()
                    ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                    ->with('success', 'Balance paid. Full course access is unlocked. Your subscription is free for one month, then monthly billing starts.');
            }

            if (in_array($offerMode, ['full_launch', 'upfront_subscription'], true) && $course) {
                if ($order->mode === 'subscription' && ! empty($order->subscription)) {
                    $this->subscriptionService->activateFromCheckoutSession($order);
                } else {
                    return redirect()
                        ->route('payments.index', ['from' => $from, 'item' => $item_type, 'id' => $item_id])
                        ->with('error', 'Payment was not completed. Please try again.');
                }

                $this->sendWelcomeEmailForCourse($user, $course);
                app(CourseSectionService::class)
                    ->initWatchHistory((string) $course->id, 'lesson', (string) $user->id);

                if ($from == 'api') {
                    return redirect()->to(env('FRONTEND_URL').'/student');
                }

                return redirect()
                    ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                    ->with('success', 'Enrollment complete. You paid the course price and the first month of Project Plans today. Monthly billing continues from next month.');
            }

            if (
                $billing_model === CourseBillingModel::SUBSCRIPTION->value
                || $billing_model === CourseBillingModel::UPFRONT_SUBSCRIPTION->value
                || $order->mode === 'subscription'
            ) {
                $this->subscriptionService->activateFromCheckoutSession($order);

                if ($course) {
                    $this->sendWelcomeEmailForCourse($user, $course);
                    app(CourseSectionService::class)
                        ->initWatchHistory((string) $course->id, 'lesson', (string) $user->id);
                }

                if ($from == 'api') {
                    return redirect()->to(env('FRONTEND_URL').'/student');
                }

                if ($course) {
                    $message = match ($offerMode) {
                        'full_launch', 'upfront_subscription' => 'Enrollment complete. You paid the course price and the first month of Project Plans today. Monthly billing continues from next month.',
                        default => 'Subscription active. You now have access to this course.',
                    };

                    return redirect()
                        ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                        ->with('success', $message);
                }
            }

            if ($order->payment_status !== 'paid') {
                return redirect()
                    ->route('payments.index', ['from' => $from, 'item' => $item_type, 'id' => $item_id])
                    ->with('error', 'Payment was not completed. Please try again.');
            }

            if ($course && $this->externalCheckout->hasActiveCourseAccess($user, $course)) {
                return redirect()
                    ->route('course.details', ['slug' => $course->slug, 'id' => $course->id])
                    ->with('success', 'You are already enrolled in this course.');
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

            if ($course) {
                $this->sendWelcomeEmailForCourse($user, $course);
            }

            if ($from == 'api') {
                return redirect()->to(env('FRONTEND_URL').'/student');
            }

            if ($course) {
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

        if (! $temp) {
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

    protected function resolveSubmittedCoupon(Course $course, mixed $code, int|string|null $userId): mixed
    {
        $code = trim((string) $code);

        if ($code === '') {
            return null;
        }

        return $this->courseCoupon->getCourseValidCoupon((string) $course->id, $code, $userId)
            ?: $this->courseCoupon->getCourseValidCoupon((string) $course->id, $code, null);
    }

    /**
     * @param  object{code?: string}|null  $coupon
     * @param  array{couponDiscount?: float|int|string, subtotal?: float|int|string}  $pricing
     * @return array{name: string, description?: string}
     */
    protected function stripeProductDataForVoucher(string $baseName, ?object $coupon, array $pricing): array
    {
        $discount = (float) ($pricing['couponDiscount'] ?? 0);
        $data = [
            'name' => PaymentVoucherCopy::stripeLineName($baseName, $coupon?->code, $discount),
        ];

        $description = $this->stripeVoucherDescription($coupon, $pricing);
        if ($description) {
            $data['description'] = $description;
        }

        return $data;
    }

    /**
     * @param  object{code?: string}|null  $coupon
     * @param  array{couponDiscount?: float|int|string, subtotal?: float|int|string}  $pricing
     */
    protected function stripeVoucherDescription(?object $coupon, array $pricing): ?string
    {
        return PaymentVoucherCopy::stripeLineDescription(
            $coupon?->code,
            (float) ($pricing['couponDiscount'] ?? 0),
            (float) ($pricing['subtotal'] ?? 0),
        );
    }

    protected function sendWelcomeEmailForCourse($user, Course $course): void
    {
        $enrollment = CourseEnrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->with(['user', 'course.instructor.user'])
            ->first();

        if (! $enrollment) {
            return;
        }

        app(CourseEnrollmentWelcomeMailService::class)->sendForEnrollment($enrollment);
    }
}
