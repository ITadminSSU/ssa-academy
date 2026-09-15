<?php

namespace App\Services\Course;

use App\Enums\EnrollmentAccessStatus;
use App\Mail\CourseEnrollmentWelcomeMail;
use App\Models\Course\CourseEnrollment;
use App\Models\User;
use App\Support\CourseWelcomeEmailCopy;
use App\Support\CourseWelcomePaymentBreakdown;
use App\Support\PaymentVoucherCopy;
use App\Support\TransactionalMailSender;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CourseEnrollmentWelcomeMailService
{
    public function __construct(
        private TransactionalMailSender $mailSender,
        private CourseWelcomePaymentBreakdown $paymentBreakdown,
        private CompanionCourseEnrollmentService $companionCourse,
    ) {}

    public function sendForPaidCoursePurchase(int|string $userId, int|string $courseId, bool $force = false): bool
    {
        $enrollment = CourseEnrollment::query()
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->with(['user', 'course.instructor.user', 'course.course_category'])
            ->first();

        if (! $enrollment) {
            return false;
        }

        return $this->sendForEnrollment($enrollment, $force);
    }

    public function sendForEnrollment(CourseEnrollment $enrollment, bool $force = false): bool
    {
        try {
            return $this->sendForEnrollmentUnsafe($enrollment, $force);
        } catch (\Throwable $exception) {
            Log::warning('Course enrollment welcome email failed', [
                'enrollment_id' => $enrollment->id ?? null,
                'user_id' => $enrollment->user_id ?? null,
                'course_id' => $enrollment->course_id ?? null,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function sendForEnrollmentUnsafe(CourseEnrollment $enrollment, bool $force = false): bool
    {
        $enrollment->loadMissing(['user', 'course.instructor.user', 'course.course_category']);

        if ((! $force && $enrollment->welcome_email_sent_at) || ! $enrollment->user || ! $enrollment->course) {
            return false;
        }

        if (! $this->shouldSend($enrollment)) {
            return false;
        }

        if (! $force && $this->paymentBreakdown->shouldWaitForPayment($enrollment)) {
            return false;
        }

        $user = $enrollment->user;
        $course = $enrollment->course;
        $instructor = $course->instructor;
        $bio = $this->shortBio($instructor?->biography);
        $academyName = (string) config('branding.name', config('app.name'));
        $breakdown = $this->paymentBreakdown->build($enrollment);
        $variant = CourseWelcomeEmailCopy::resolveVariant($course);
        $intro = 'Thank you for your payment of '.$this->money($breakdown['this_payment_amount']).' for “'.$course->title.'”.';

        if ($breakdown['coupon_code'] !== '') {
            $voucherLabel = 'Voucher '.$breakdown['coupon_code'];
            $percentLabel = PaymentVoucherCopy::percentLabel($breakdown['discount_percent']);

            if ($breakdown['discount_amount'] > 0 && $percentLabel !== '') {
                $intro .= ' '.$voucherLabel.' was applied (−'.$this->money($breakdown['discount_amount']).', '.$percentLabel.' off).';
            } elseif ($breakdown['discount_amount'] > 0) {
                $intro .= ' '.$voucherLabel.' was applied (−'.$this->money($breakdown['discount_amount']).').';
            } elseif ($percentLabel !== '') {
                $intro .= ' '.$voucherLabel.' was applied ('.$percentLabel.' off).';
            } else {
                $intro .= ' '.$voucherLabel.' was applied.';
            }
        } else {
            $intro .= ' This completes your course payment.';
        }

        $intro .= ' You have successfully enrolled and we’re excited to have you join '.$academyName.'.';

        $bodyParagraphs = CourseWelcomeEmailCopy::bodyParagraphs($variant);
        $ctas = [
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
        ];

        $companion = $this->companionCourse->welcomeCompanionCourse($course);
        $companionCourseTitle = null;
        $companionHighlightRest = null;

        if ($companion && filled($companion->title) && filled($companion->slug)) {
            $companionCourseTitle = (string) $companion->title;
            $companionHighlightRest = CourseWelcomeEmailCopy::companionAccessFollowUp();
            array_splice($ctas, 2, 0, [CourseWelcomeEmailCopy::companionAccessCta(route('course.details', [
                'slug' => $companion->slug,
                'id' => $companion->id,
            ]))]);
        }

        $sent = $this->send($user, new CourseEnrollmentWelcomeMail(
            emailSubject: 'Welcome to '.$course->title.'!',
            greeting: 'Hi '.$this->firstName($user).',',
            courseTitle: (string) $course->title,
            introParagraphs: [
                $intro,
            ],
            paymentBullets: $breakdown['bullets'],
            bodyParagraphs: $bodyParagraphs,
            instructorName: $instructor?->user?->name ?: $instructor?->designation,
            instructorBio: $bio !== '' ? $bio : null,
            ctas: $ctas,
            closingNote: 'Thank you for trusting '.$academyName.' with your learning journey. We look forward to supporting you as you build your skills and prepare for new opportunities.',
            farewell: 'Best regards,',
            signatureName: $academyName.' Team',
            companionCourseTitle: $companionCourseTitle,
            companionHighlightRest: $companionHighlightRest,
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

    private function send(User $user, CourseEnrollmentWelcomeMail $mailable): bool
    {
        return $this->mailSender->send($user, $mailable, 'Course enrollment welcome email');
    }
}
