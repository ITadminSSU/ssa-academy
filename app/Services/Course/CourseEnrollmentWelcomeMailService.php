<?php

namespace App\Services\Course;

use App\Enums\EnrollmentAccessStatus;
use App\Enums\PaymentBillingType;
use App\Mail\CourseEnrollmentWelcomeMail;
use App\Models\Course\Course;
use App\Models\Course\CourseCoupon;
use App\Models\Course\CourseEnrollment;
use App\Models\User;
use App\Services\Payment\LaunchOfferService;
use App\Support\CourseWelcomeEmailCopy;
use App\Support\PaymentVoucherCopy;
use App\Support\TransactionalMailSender;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Modules\PaymentGateways\Models\PaymentHistory;
use Modules\PaymentGateways\Services\PaymentService;

class CourseEnrollmentWelcomeMailService
{
    public function __construct(
        private TransactionalMailSender $mailSender,
        private LaunchOfferService $launchOffer,
        private PaymentService $paymentService,
    ) {}

    public function sendForEnrollment(CourseEnrollment $enrollment, bool $force = false): bool
    {
        $enrollment->loadMissing(['user', 'course.instructor.user', 'course.course_category']);

        if ((! $force && $enrollment->welcome_email_sent_at) || ! $enrollment->user || ! $enrollment->course) {
            return false;
        }

        if (! $this->shouldSend($enrollment)) {
            return false;
        }

        $user = $enrollment->user;
        $course = $enrollment->course;
        $instructor = $course->instructor;
        $bio = $this->shortBio($instructor?->biography);
        $academyName = (string) config('branding.name', config('app.name'));
        $breakdown = $this->paymentBreakdown($enrollment);
        $variant = CourseWelcomeEmailCopy::resolveVariant($course);
        $intro = 'Thank you for your payment of '.$this->money($breakdown['this_payment_amount']).' for “'.$course->title.'”.';

        if ($breakdown['discount_amount'] > 0) {
            $voucherLabel = $breakdown['coupon_code'] !== ''
                ? 'Voucher '.$breakdown['coupon_code']
                : 'A voucher';
            $intro .= ' '.$voucherLabel.' was applied (−'.$this->money($breakdown['discount_amount']).').';
        } else {
            $intro .= ' This completes your course payment.';
        }

        $intro .= ' You have successfully enrolled and we’re excited to have you join '.$academyName.'.';

        $sent = $this->send($user, new CourseEnrollmentWelcomeMail(
            emailSubject: 'Welcome to '.$course->title.'!',
            greeting: 'Hi '.$this->firstName($user).',',
            courseTitle: (string) $course->title,
            introParagraphs: [
                $intro,
            ],
            paymentBullets: $breakdown['bullets'],
            bodyParagraphs: CourseWelcomeEmailCopy::bodyParagraphs($variant),
            instructorName: $instructor?->user?->name ?: $instructor?->designation,
            instructorBio: $bio !== '' ? $bio : null,
            ctas: [
                [
                    'label' => 'Join Our Facebook Community',
                    'url' => $this->facebookGroupUrl(),
                    'description' => 'Connect with other learners, ask questions, get support from your instructor, and stay updated with the latest announcements and course information.',
                    'button_color' => '#1877F2',
                ],
                [
                    'label' => 'Explore your course',
                    'url' => route('course.details', [
                        'slug' => $course->slug,
                        'id' => $course->id,
                    ]),
                    'description' => 'Ready to start learning? Your course materials are waiting for you.',
                    'button_color' => '#8C2A23',
                ],
                [
                    'label' => 'Follow Our Facebook Page',
                    'url' => $this->facebookPageUrl(),
                    'description' => 'Connect with SMARTSOURCING USA and be updated with job opportunities and other important updates in the construction industry.',
                    'button_color' => '#1877F2',
                ],
            ],
            closingNote: 'Thank you for trusting '.$academyName.' with your learning journey. We look forward to supporting you as you build your skills and prepare for new opportunities.',
            farewell: 'Best regards,',
            signatureName: $academyName.' Team',
        ));

        if ($sent) {
            $enrollment->forceFill(['welcome_email_sent_at' => now()])->save();
        }

        return $sent;
    }

    private function shouldSend(CourseEnrollment $enrollment): bool
    {
        $status = $enrollment->access_status;

        if ($status === EnrollmentAccessStatus::RESERVED
            || $status === EnrollmentAccessStatus::CANCELED
            || $status === EnrollmentAccessStatus::EXPIRED
            || $status === EnrollmentAccessStatus::SUSPENDED
        ) {
            return false;
        }

        return $status === EnrollmentAccessStatus::ACTIVE || $status === null;
    }

    /**
     * @return array{this_payment_amount: float, discount_amount: float, coupon_code: string, bullets: list<string>}
     */
    private function paymentBreakdown(CourseEnrollment $enrollment): array
    {
        $depositAmount = (float) ($enrollment->deposit_amount ?? 0);
        $depositDate = $enrollment->deposit_paid_at;

        $thisPayment = $this->resolveCompletingPayment($enrollment);
        $thisPaymentAmount = (float) ($thisPayment?->amount ?? 0);
        $thisPaymentDate = $enrollment->balance_paid_at
            ?? $thisPayment?->created_at
            ?? $enrollment->entry_date
            ?? now();

        $expectedSubtotal = $this->expectedSubtotalBeforeDiscount($enrollment, $thisPayment);
        $voucher = $this->resolveVoucher($thisPayment, $expectedSubtotal, $thisPaymentAmount);
        $discountAmount = $voucher['amount'];
        $couponCode = $voucher['code'];

        if ($discountAmount > 0) {
            $afterDiscount = round(max(0, $expectedSubtotal - $discountAmount), 2);
            if ($thisPaymentAmount <= 0 || abs($thisPaymentAmount - $expectedSubtotal) < 0.009) {
                $thisPaymentAmount = $afterDiscount;
            }
        } elseif ($thisPaymentAmount <= 0 && $expectedSubtotal > 0) {
            $thisPaymentAmount = $expectedSubtotal;
        }

        $totalAmount = $depositAmount + $expectedSubtotal;

        if ($totalAmount <= 0) {
            $totalAmount = $thisPaymentAmount + $discountAmount;
        }

        $bullets = [];

        if ($depositAmount > 0) {
            $bullets[] = 'Pre-registration ('.$this->date($depositDate).'): '.$this->money($depositAmount);
        }

        if ($discountAmount > 0 && $expectedSubtotal > 0) {
            $priceLabel = $depositAmount > 0 ? 'Balance' : 'Course Price';
            $bullets[] = $priceLabel.': '.$this->money($expectedSubtotal);
            $bullets[] = PaymentVoucherCopy::breakdownLine($couponCode, $discountAmount);
        }

        $bullets[] = 'This Payment ('.$this->date($thisPaymentDate).'): '.$this->money($thisPaymentAmount);
        $bullets[] = 'Total Course Price: '.$this->money($totalAmount);

        return [
            'this_payment_amount' => $thisPaymentAmount,
            'discount_amount' => $discountAmount,
            'coupon_code' => $couponCode,
            'bullets' => $bullets,
        ];
    }

    private function resolveCompletingPayment(CourseEnrollment $enrollment): ?PaymentHistory
    {
        if ($enrollment->balance_payment_history_id) {
            $balancePayment = PaymentHistory::query()->find($enrollment->balance_payment_history_id);
            if ($balancePayment) {
                return $balancePayment;
            }
        }

        $query = PaymentHistory::query()
            ->where('user_id', $enrollment->user_id)
            ->where('purchase_type', Course::class)
            ->where('purchase_id', $enrollment->course_id)
            ->where(function ($q) {
                $q->whereNull('billing_type')
                    ->orWhere('billing_type', '!=', PaymentBillingType::DEPOSIT->value);
            })
            ->orderByDesc('id');

        if ($enrollment->deposit_payment_history_id) {
            $query->where('id', '!=', $enrollment->deposit_payment_history_id);
        }

        return $query->first();
    }

    private function expectedSubtotalBeforeDiscount(CourseEnrollment $enrollment, ?PaymentHistory $thisPayment): float
    {
        $course = $enrollment->course;
        $depositAmount = (float) ($enrollment->deposit_amount ?? 0);

        if ($depositAmount > 0 || ($thisPayment?->billing_type === PaymentBillingType::BALANCE)) {
            return (float) ($enrollment->balance_amount ?? $this->launchOffer->balanceAmount($course));
        }

        if ($this->launchOffer->isConfigured($course)) {
            return $this->launchOffer->fullUpfrontPrice($course);
        }

        $price = $course->discount && $course->discount_price
            ? (float) $course->discount_price
            : (float) ($course->price ?? 0);

        return max(0, $price);
    }

    /**
     * @return array{code: string, amount: float}
     */
    private function resolveVoucher(?PaymentHistory $thisPayment, float $expectedSubtotal, float $paidAmount): array
    {
        $couponCode = PaymentVoucherCopy::normalizeCode($thisPayment?->coupon);
        $discountAmount = 0.0;

        if ($couponCode !== '' && $expectedSubtotal > 0) {
            $coupon = CourseCoupon::query()
                ->whereRaw('LOWER(code) = ?', [strtolower($couponCode)])
                ->first();

            if ($coupon) {
                $pricing = $this->paymentService->calculateCustomPrice($expectedSubtotal, $coupon);
                $discountAmount = max(0, (float) ($pricing['couponDiscount'] ?? 0));
            }
        }

        if ($discountAmount <= 0 && $expectedSubtotal > 0 && $paidAmount > 0 && $expectedSubtotal > $paidAmount) {
            $discountAmount = max(0, round($expectedSubtotal - $paidAmount, 2));
        }

        return [
            'code' => $couponCode,
            'amount' => $discountAmount,
        ];
    }

    private function shortBio(?string $biography): string
    {
        $text = trim(html_entity_decode(strip_tags((string) $biography), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        if ($text === '') {
            return '';
        }

        return Str::limit($text, 600, '…');
    }

    private function facebookGroupUrl(): string
    {
        $url = trim((string) config('branding.facebook_group_url', ''));

        return $url !== '' ? $url : 'https://www.facebook.com/share/g/14ttXqLttek/';
    }

    private function facebookPageUrl(): string
    {
        $url = trim((string) config('branding.facebook_page_url', ''));

        return $url !== '' ? $url : 'https://www.facebook.com/smartsourcingusa';
    }

    private function firstName(User $user): string
    {
        $name = trim((string) ($user->name ?? ''));

        if ($name === '') {
            return 'there';
        }

        return explode(' ', $name)[0];
    }

    private function money(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }

    private function date(null|CarbonInterface|string $date): string
    {
        if (! $date) {
            return 'N/A';
        }

        if (is_string($date)) {
            $date = \Carbon\Carbon::parse($date);
        }

        return $date->timezone(config('app.timezone'))->format('F j, Y');
    }

    private function send(User $user, CourseEnrollmentWelcomeMail $mailable): bool
    {
        return $this->mailSender->send($user, $mailable, 'Course enrollment welcome email');
    }
}
