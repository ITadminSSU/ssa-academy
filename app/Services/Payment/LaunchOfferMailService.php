<?php

namespace App\Services\Payment;

use App\Mail\LaunchOfferStudentMail;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\User;
use App\Support\TransactionalMailSender;
use Carbon\CarbonInterface;

class LaunchOfferMailService
{
    public function __construct(
        private TransactionalMailSender $mailSender,
    ) {}
    public function sendDepositConfirmation(CourseEnrollment $enrollment, bool $force = false): bool
    {
        $enrollment->loadMissing(['user', 'course']);

        if ((! $force && $enrollment->deposit_confirmation_sent_at) || ! $enrollment->user || ! $enrollment->course) {
            return false;
        }

        $course = $enrollment->course;
        $user = $enrollment->user;
        $depositAmount = (float) ($enrollment->deposit_amount ?? 0);
        $balanceAmount = (float) ($enrollment->balance_amount ?? 0);
        $deposit = $this->money($depositAmount);
        $balance = $this->money($balanceAmount);
        $total = $this->money($depositAmount + $balanceAmount);
        $preRegistrationDate = $this->date($enrollment->deposit_paid_at ?? now());
        $launchDate = $this->date($enrollment->balance_due_at);
        $academyName = (string) config('branding.name', config('app.name'));

        $sent = $this->send($user, new LaunchOfferStudentMail(
            emailSubject: 'Seat reserved for '.$course->title.'.',
            greeting: 'Hi '.$this->firstName($user).',',
            paragraphs: [
                'Thank you for your deposit of '.$deposit.' as pre-registration for “'.$course->title.'”. Your seat is now reserved. Please note that this deposit is non-refundable.',
            ],
            bullets: [
                'Pre-registration ('.$preRegistrationDate.'): '.$deposit,
                'Balance: '.$balance,
                'Total Course Price: '.$total,
            ],
            closingNote: 'Thank you for trusting '.$academyName.' with your learning journey. We’re excited to have you with us and look forward to supporting you throughout your course.',
            bulletsHeading: 'Payment Breakdown:',
            ctas: [
                [
                    'label' => 'Follow Our Facebook Page',
                    'url' => $this->facebookPageUrl(),
                    'description' => 'Connect with SMARTSOURCING USA and be updated with job opportunities, discount vouchers, and other important updates in the construction industry.',
                ],
                [
                    'label' => 'Explore all courses',
                    'url' => url('/courses/all'),
                    'description' => 'Explore the other courses available through '.$academyName.' and find the next course to support your career goals.',
                ],
            ],
            farewell: 'Best regards,',
            signatureName: $academyName.' Team',
            postParagraphs: [
                'You may pay the remaining balance of '.$balance.' on launch day ('.$launchDate.') to unlock full access to the course.',
                'Keep an eye out for discount vouchers on our official Facebook page or from your referrer for huge savings!',
            ],
        ));

        if ($sent) {
            $enrollment->forceFill(['deposit_confirmation_sent_at' => now()])->save();
        }

        return $sent;
    }

    public function sendBalanceDueNotice(CourseEnrollment $enrollment, bool $force = false): bool
    {
        $enrollment->loadMissing(['user', 'course']);

        if ((! $force && $enrollment->balance_due_notice_sent_at) || ! $enrollment->isReservedSeat() || ! $enrollment->user || ! $enrollment->course) {
            return false;
        }

        $course = $enrollment->course;
        $user = $enrollment->user;
        $balance = $this->money((float) ($enrollment->balance_amount ?? 0));
        $deadline = $this->date($enrollment->balance_deadline_at);

        $sent = $this->send($user, new LaunchOfferStudentMail(
            emailSubject: 'Pay your balance — '.$course->title,
            greeting: 'Hi '.$this->firstName($user).',',
            paragraphs: [
                'Launch day is here for “'.$course->title.'”. Pay your remaining balance of '.$balance.' to unlock full course access.',
                'If the balance is not paid by '.$deadline.', your reserved seat will be canceled and your deposit will be kept.',
            ],
            bullets: [
                'Amount due: '.$balance,
                'Pay by: '.$deadline,
            ],
            ctaLabel: 'Pay balance',
            ctaUrl: $this->courseUrl($course),
        ));

        if ($sent) {
            $enrollment->forceFill(['balance_due_notice_sent_at' => now()])->save();
        }

        return $sent;
    }

    public function sendBalanceMidReminder(CourseEnrollment $enrollment, bool $force = false): bool
    {
        $enrollment->loadMissing(['user', 'course']);

        if ((! $force && $enrollment->balance_mid_reminder_sent_at) || ! $enrollment->isReservedSeat() || ! $enrollment->user || ! $enrollment->course) {
            return false;
        }

        $course = $enrollment->course;
        $user = $enrollment->user;
        $balance = $this->money((float) ($enrollment->balance_amount ?? 0));
        $deadline = $this->date($enrollment->balance_deadline_at);

        $sent = $this->send($user, new LaunchOfferStudentMail(
            emailSubject: 'Reminder: balance still due — '.$course->title,
            greeting: 'Hi '.$this->firstName($user).',',
            paragraphs: [
                'This is a mid-grace reminder for “'.$course->title.'”. Your remaining balance of '.$balance.' is still unpaid.',
                'Please pay by '.$deadline.' to keep your seat and unlock the course. After that date, the reservation is canceled and the deposit is non-refundable.',
            ],
            bullets: [
                'Amount due: '.$balance,
                'Final deadline: '.$deadline,
            ],
            ctaLabel: 'Pay balance now',
            ctaUrl: $this->courseUrl($course),
        ));

        if ($sent) {
            $enrollment->forceFill(['balance_mid_reminder_sent_at' => now()])->save();
        }

        return $sent;
    }

    public function sendBalanceFinalReminder(CourseEnrollment $enrollment, bool $force = false): bool
    {
        $enrollment->loadMissing(['user', 'course']);

        if ((! $force && $enrollment->balance_final_reminder_sent_at) || ! $enrollment->isReservedSeat() || ! $enrollment->user || ! $enrollment->course) {
            return false;
        }

        $course = $enrollment->course;
        $user = $enrollment->user;
        $balance = $this->money((float) ($enrollment->balance_amount ?? 0));
        $deadline = $this->date($enrollment->balance_deadline_at);

        $sent = $this->send($user, new LaunchOfferStudentMail(
            emailSubject: 'Final notice: pay today — '.$course->title,
            greeting: 'Hi '.$this->firstName($user).',',
            paragraphs: [
                'Today is the final day to pay the remaining '.$balance.' for “'.$course->title.'”.',
                'If payment is not completed by '.$deadline.', your reserved seat will be canceled automatically and your deposit will be kept.',
            ],
            bullets: [
                'Amount due: '.$balance,
                'Deadline: '.$deadline,
            ],
            ctaLabel: 'Pay balance today',
            ctaUrl: $this->courseUrl($course),
        ));

        if ($sent) {
            $enrollment->forceFill(['balance_final_reminder_sent_at' => now()])->save();
        }

        return $sent;
    }

    public function sendBalancePaidConfirmation(CourseEnrollment $enrollment, bool $force = false): bool
    {
        // Superseded by the full welcome email (payment breakdown + category copy).
        // Kept for optional manual resend via ssu:resend-launch-offer-emails --type=balance-paid.
        $enrollment->loadMissing(['user', 'course']);

        if ((! $force && $enrollment->balance_paid_confirmation_sent_at) || ! $enrollment->user || ! $enrollment->course) {
            return false;
        }

        $course = $enrollment->course;
        $user = $enrollment->user;
        $balance = $this->money((float) ($enrollment->balance_amount ?? 0));

        $sent = $this->send($user, new LaunchOfferStudentMail(
            emailSubject: 'You are fully enrolled — '.$course->title,
            greeting: 'Hi '.$this->firstName($user).',',
            paragraphs: [
                'We received your balance payment of '.$balance.' for “'.$course->title.'”.',
                'You are fully enrolled and can start the course now.',
            ],
            bullets: [],
            ctaLabel: 'Open course',
            ctaUrl: $this->courseUrl($course),
        ));

        if ($sent) {
            $enrollment->forceFill(['balance_paid_confirmation_sent_at' => now()])->save();
        }

        return $sent;
    }

    public function sendForfeitNotice(CourseEnrollment $enrollment, bool $force = false): bool
    {
        $enrollment->loadMissing(['user', 'course']);

        if ((! $force && $enrollment->forfeit_notice_sent_at) || ! $enrollment->user || ! $enrollment->course) {
            return false;
        }

        $course = $enrollment->course;
        $user = $enrollment->user;
        $deposit = $this->money((float) ($enrollment->deposit_amount ?? 0));
        $deadline = $this->date($enrollment->balance_deadline_at);

        $sent = $this->send($user, new LaunchOfferStudentMail(
            emailSubject: 'Seat canceled — '.$course->title,
            greeting: 'Hi '.$this->firstName($user).',',
            paragraphs: [
                'Your reserved seat for “'.$course->title.'” was canceled because the remaining balance was not paid by '.$deadline.'.',
                'Per the launch offer terms, your deposit of '.$deposit.' is non-refundable and has been retained.',
            ],
            bullets: [],
            ctaLabel: 'Browse courses',
            ctaUrl: url('/'),
            closingNote: 'If you still want to enroll, you may purchase at the current course price when available.',
        ));

        if ($sent) {
            $enrollment->forceFill(['forfeit_notice_sent_at' => now()])->save();
        }

        return $sent;
    }

    private function send(User $user, LaunchOfferStudentMail $mailable): bool
    {
        return $this->mailSender->send(
            $user,
            $mailable,
            'Launch offer student email',
            $this->adminBccAddresses(),
        );
    }

    /**
     * @return list<string>
     */
    private function adminBccAddresses(): array
    {
        $raw = (string) config('payment.launch_offer.admin_bcc', '');

        return collect(explode(',', $raw))
            ->map(fn (string $email) => trim($email))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->values()
            ->all();
    }

    private function courseUrl(Course $course): string
    {
        return route('course.details', [
            'slug' => $course->slug,
            'id' => $course->id,
        ]);
    }

    private function facebookPageUrl(): string
    {
        $url = trim((string) config('branding.facebook_page_url', ''));

        if ($url !== '') {
            return $url;
        }

        return 'https://www.facebook.com/smartsourcingusa';
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

    private function date(?CarbonInterface $date): string
    {
        if (! $date) {
            return 'the deadline shown on your course page';
        }

        return $date->timezone(config('app.timezone'))->format('F j, Y');
    }
}
