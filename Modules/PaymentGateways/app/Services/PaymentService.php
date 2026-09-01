<?php

namespace Modules\PaymentGateways\Services;

use App\Enums\EnrollmentAccessStatus;
use App\Enums\PaymentBillingType;
use App\Enums\PaymentRefundStatus;
use App\Enums\UserType;
use App\Models\Course\Course;
use App\Models\Course\CourseCoupon;
use App\Models\Course\CourseEnrollment;
use App\Models\Instructor;
use App\Models\Subscription;
use Modules\PaymentGateways\Models\PaymentHistory;
use App\Services\Course\CourseEnrollmentService;
use App\Services\Course\CourseEnrollmentWelcomeMailService;
use App\Services\Course\CourseService;
use App\Services\Course\CourseCouponService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Modules\Exam\Models\Exam;
use Modules\Exam\Services\ExamCouponService;
use Modules\Exam\Services\ExamEnrollmentService;
use Modules\Exam\Services\ExamService;
use Modules\Exam\Models\ExamCoupon;

class PaymentService
{
    public function __construct(
        private ExamService $examService,
        private CourseService $courseService,
        private CourseCouponService $courseCoupon,
        private CourseEnrollmentService $courseEnrollment,
        private ExamEnrollmentService $examEnrollment,
        private ExamCouponService $examCoupon,

    ) {}

    public function getCheckoutItem(string $item_type, string $item_id, ?string $coupon_code)
    {
        $item = null;
        $coupon = null;

        if ($item_type === 'course') {
            $item = $this->courseService->getCheckoutCourse($item_id);

            if ($coupon_code) {
                $coupon = $this->courseCoupon->getCourseValidCoupon(
                    $item_id,
                    $coupon_code,
                    Auth::id(),
                );
            }
        } else {
            $item = $this->examService->getCheckoutExam($item_id);

            if ($coupon_code) {
                $coupon = $this->examCoupon->getExamValidCoupon($item_id, $coupon_code);
            }
        }

        $calculatedItemPrice = $this->calculateItemPrice($item, $coupon);

        return [
            'item' => $item,
            'coupon' => $coupon,
            ...$calculatedItemPrice,
        ];
    }

    public function validateExamCoupons(string $item_type, string $item_id)
    {
        if ($item_type === 'exam') {
            return $this->examCoupon->getExamValidCoupons($item_id);
        }

        return $this->courseCoupon->getCourseValidCoupons($item_id);
    }

    public function coursesBuy(
        string $paymentMethod,
        string $item_type,
        string $item_id,
        string $transactionId,
        float $taxAmount,
        float $totalPrice,
        ?string $couponCode,
        ?string $user_id = null
    ) {
        $user_id = $user_id ?? Auth::user()->id;
        $invoice_no = random_int(10000000, 99999999);
        $instructorRevenue = app('system_settings')->fields['instructor_revenue'];

        // Initialize variables to avoid undefined errors
        $instructor = null;
        $historyData = [];

        if ($existing = PaymentHistory::where('transaction_id', $transactionId)->first()) {
            $this->applyCouponToPayment($existing, $couponCode);
            $this->sendWelcomeForOneTimeCoursePurchase($user_id, $item_type, $item_id);

            return;
        }

        // Handle course purchase
        if ($item_type === 'course') {
            $course = Course::findOrFail($item_id);
            $instructor = Instructor::with('user')
                ->where('id', $course->instructor_id)
                ->first();

            $historyData = [
                'purchase_type' => Course::class,
                'purchase_id' => $course->id,
            ];

            if ($paymentMethod !== 'offline') {
                $alreadyEnrolled = CourseEnrollment::query()
                    ->where('user_id', $user_id)
                    ->where('course_id', $course->id)
                    ->exists();

                if (!$alreadyEnrolled) {
                    $this->courseEnrollment->createCourseEnroll([
                        'user_id' => $user_id,
                        'course_id' => $course->id,
                        'enrollment_type' => 'paid',
                        'access_status' => EnrollmentAccessStatus::ACTIVE->value,
                    ], sendWelcome: false);
                }
            }

            // $this->cartService->clearCart($user_id);
        }

        // Handle exam purchase
        if ($item_type === 'exam') {
            $exam = Exam::findOrFail($item_id);
            $instructor = Instructor::with('user')
                ->where('id', $exam->instructor_id)
                ->first();

            $historyData = [
                'purchase_type' => Exam::class,
                'purchase_id' => $exam->id,
            ];

            if ($paymentMethod !== 'offline') {
                $this->examEnrollment->createExamEnroll([
                    'user_id' => $user_id,
                    'exam_id' => $exam->id,
                    'enrollment_type' => 'paid',
                ]);
            }
        }

        // Calculate revenue split
        if ($instructor->user->role == UserType::ADMIN->value) {
            $historyData['admin_revenue'] = $totalPrice;
        } else {
            $instructorRevenueAmount = $totalPrice * ($instructorRevenue / 100);
            $historyData['instructor_revenue'] = $instructorRevenueAmount - $taxAmount;
            $historyData['admin_revenue'] = ($totalPrice - $instructorRevenueAmount) + $taxAmount;
        }

        // Create payment history
        PaymentHistory::create([
            'user_id' => $user_id,
            'amount' => $totalPrice,
            'tax' => $taxAmount,
            'payment_type' => $paymentMethod,
            'coupon' => $couponCode,
            'transaction_id' => $transactionId,
            'invoice' => $invoice_no,
            'refund_status' => PaymentRefundStatus::PAID->value,
            'billing_type' => PaymentBillingType::ONE_TIME,
            ...$historyData,
        ]);

        $history = PaymentHistory::query()
            ->where('transaction_id', $transactionId)
            ->latest('id')
            ->first();

        if ($history) {
            $this->applyCouponToPayment($history, $couponCode);
        }

        $this->sendWelcomeForOneTimeCoursePurchase($user_id, $item_type, $item_id);
    }

    private function sendWelcomeForOneTimeCoursePurchase(int|string $userId, string $itemType, string $itemId): void
    {
        if ($itemType !== 'course') {
            return;
        }

        app(CourseEnrollmentWelcomeMailService::class)->sendForPaidCoursePurchase($userId, $itemId);
    }

    public function recordSubscriptionPayment(
        Subscription $subscription,
        string $transactionId,
        float $totalPrice,
        float $taxAmount,
        PaymentBillingType $billingType,
        ?string $couponCode = null,
    ): void {
        if ($existing = PaymentHistory::where('transaction_id', $transactionId)->first()) {
            $this->applyCouponToPayment($existing, $couponCode);

            return;
        }

        $course = Course::findOrFail($subscription->course_id);
        $instructor = Instructor::with('user')
            ->where('id', $course->instructor_id)
            ->first();

        $historyData = [
            'purchase_type' => Course::class,
            'purchase_id' => $course->id,
            'subscription_id' => $subscription->id,
            'billing_type' => $billingType,
        ];

        if ($instructor->user->role == UserType::ADMIN->value) {
            $historyData['admin_revenue'] = $totalPrice;
        } else {
            $instructorRevenue = app('system_settings')->fields['instructor_revenue'];
            $instructorRevenueAmount = $totalPrice * ($instructorRevenue / 100);
            $historyData['instructor_revenue'] = $instructorRevenueAmount - $taxAmount;
            $historyData['admin_revenue'] = ($totalPrice - $instructorRevenueAmount) + $taxAmount;
        }

        $history = PaymentHistory::create([
            'user_id' => $subscription->user_id,
            'amount' => $totalPrice,
            'tax' => $taxAmount,
            'payment_type' => 'stripe',
            'coupon' => $couponCode,
            'transaction_id' => $transactionId,
            'invoice' => random_int(10000000, 99999999),
            'refund_status' => PaymentRefundStatus::PAID->value,
            ...$historyData,
        ]);

        $this->applyCouponToPayment($history, $couponCode);
    }

    public function recordLaunchOfferPayment(
        \App\Models\User $user,
        Course $course,
        string $paymentMethod,
        string $transactionId,
        float $amount,
        PaymentBillingType $billingType,
        ?string $couponCode = null,
        ?float $couponDiscount = null,
    ): PaymentHistory {
        if ($existing = PaymentHistory::where('transaction_id', $transactionId)->first()) {
            $this->applyCouponToPayment($existing, $couponCode, $couponDiscount);

            return $existing;
        }

        $instructor = Instructor::with('user')
            ->where('id', $course->instructor_id)
            ->first();

        $historyData = [
            'purchase_type' => Course::class,
            'purchase_id' => $course->id,
            'billing_type' => $billingType,
        ];

        if ($instructor && $instructor->user->role == UserType::ADMIN->value) {
            $historyData['admin_revenue'] = $amount;
        } elseif ($instructor) {
            $instructorRevenue = app('system_settings')->fields['instructor_revenue'];
            $instructorRevenueAmount = $amount * ($instructorRevenue / 100);
            $historyData['instructor_revenue'] = $instructorRevenueAmount;
            $historyData['admin_revenue'] = $amount - $instructorRevenueAmount;
        } else {
            $historyData['admin_revenue'] = $amount;
        }

        $history = PaymentHistory::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'tax' => 0,
            'payment_type' => $paymentMethod,
            'coupon' => $couponCode,
            'transaction_id' => $transactionId,
            'invoice' => random_int(10000000, 99999999),
            'refund_status' => PaymentRefundStatus::PAID->value,
            ...$historyData,
        ]);

        $this->applyCouponToPayment($history, $couponCode, $couponDiscount);

        return $history;
    }

    public function calculateItemPrice(Exam|Course $item, ExamCoupon|CourseCoupon|null $coupon): array
    {
        // dd($coupon);
        $subtotal = round($item->discount ? $item->discount_price : $item->price, 2);
        $sellingTax = app('system_settings')->fields['selling_tax'];

        // Calculate coupon discount based on discount type
        $couponDiscount = 0;
        if ($coupon) {
            if ($coupon->discount_type === 'percentage') {
                // Calculate percentage discount from subtotal
                $couponDiscount = round(($subtotal * $coupon->discount) / 100, 2);
            } else {
                // Fixed discount amount
                $couponDiscount = round($coupon->discount, 2);
            }
        }

        $discountedPrice = round($subtotal - $couponDiscount, 2);
        $taxAmount = config('payment.apply_selling_tax')
            ? round(($discountedPrice * $sellingTax) / 100, 2)
            : 0;
        $finalPrice = round($discountedPrice + $taxAmount, 2);

        return [
            'subtotal' => $subtotal,
            'taxAmount' => $taxAmount,
            'couponDiscount' => $couponDiscount,
            'discountedPrice' => $discountedPrice,
            'finalPrice' => $finalPrice
        ];
    }

    public function calculateCustomPrice(float $subtotal, CourseCoupon|ExamCoupon|null $coupon, bool $applyTax = false): array
    {
        $subtotal = round($subtotal, 2);
        $sellingTax = app('system_settings')->fields['selling_tax'];

        $couponDiscount = 0;
        if ($coupon) {
            $couponDiscount = $coupon->discount_type === 'percentage'
                ? round(($subtotal * $coupon->discount) / 100, 2)
                : round($coupon->discount, 2);
        }

        $couponDiscount = min($couponDiscount, $subtotal);
        $discountedPrice = round($subtotal - $couponDiscount, 2);
        $taxAmount = $applyTax && config('payment.apply_selling_tax')
            ? round(($discountedPrice * $sellingTax) / 100, 2)
            : 0;
        $finalPrice = round($discountedPrice + $taxAmount, 2);

        return [
            'subtotal' => $subtotal,
            'taxAmount' => $taxAmount,
            'couponDiscount' => $couponDiscount,
            'discountedPrice' => $discountedPrice,
            'finalPrice' => $finalPrice,
        ];
    }

    public function applyCouponToPayment(PaymentHistory $payment, ?string $couponCode, ?float $discount = null): void
    {
        $couponCode = strtoupper(trim((string) $couponCode));

        if ($couponCode === '' && ($discount === null || $discount <= 0)) {
            return;
        }

        if ($couponCode !== '' && strtoupper(trim((string) $payment->coupon)) !== $couponCode) {
            $payment->forceFill(['coupon' => $couponCode])->save();
        }

        $meta = is_array($payment->meta) ? $payment->meta : [];
        $dirty = false;

        if ($couponCode !== '' && ($meta['coupon_code'] ?? '') === '') {
            $meta['coupon_code'] = $couponCode;
            $dirty = true;
        }

        if ($discount !== null && $discount > 0 && (float) ($meta['coupon_discount'] ?? 0) <= 0) {
            $meta['coupon_discount'] = round($discount, 2);
            $dirty = true;
        }

        if ($dirty) {
            $payment->forceFill(['meta' => $meta])->save();
        }

        if ($couponCode !== '') {
            $this->syncCouponUsedCount($couponCode);
        }
    }

    public function syncCouponUsedCount(string $code): void
    {
        $code = trim($code);

        if ($code === '') {
            return;
        }

        $count = PaymentHistory::query()
            ->whereRaw('LOWER(coupon) = ?', [strtolower($code)])
            ->count();

        CourseCoupon::query()
            ->whereRaw('LOWER(code) = ?', [strtolower($code)])
            ->update(['used_count' => $count]);
    }

    /**
     * Convert currency using external API (Optional upgrade)
     * Uncomment the API call in convertCurrency() to use this
     * 
     * @param float $amount
     * @param string $fromCurrency
     * @param string $toCurrency
     * @return float|null
     */
    private function convertCurrencyWithAPI($amount, $fromCurrency, $toCurrency)
    {
        try {
            // Using free ExchangeRate-API (no API key required)
            $response = Http::timeout(5)->get("https://api.exchangerate-api.com/v4/latest/{$fromCurrency}");

            if ($response->successful()) {
                $data = $response->json();
                $rate = $data['rates'][$toCurrency] ?? null;

                if ($rate) {
                    return round($amount * $rate, 2);
                }
            }
        } catch (\Exception $e) {
            // API failed, fall back to fixed rates
        }

        return null;
    }
}
