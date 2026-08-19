<?php

namespace App\Console\Commands;

use App\Models\Course\CourseEnrollment;
use App\Services\Course\CourseEnrollmentWelcomeMailService;
use App\Services\Payment\LaunchOfferMailService;
use Illuminate\Console\Command;

class ResendLaunchOfferEmailsCommand extends Command
{
    protected $signature = 'ssu:resend-launch-offer-emails
                            {enrollment : Course enrollment ID}
                            {--type=deposit : deposit, balance-due, balance-mid, balance-final, balance-paid, welcome, forfeit, or all}
                            {--force : Send even if that email was already recorded as sent}';

    protected $description = 'Resend pre-registration / launch offer student emails for testing or recovery';

    public function handle(
        LaunchOfferMailService $launchMail,
        CourseEnrollmentWelcomeMailService $welcomeMail,
    ): int {
        $enrollment = CourseEnrollment::query()
            ->with(['user', 'course.instructor.user'])
            ->find($this->argument('enrollment'));

        if (! $enrollment) {
            $this->error('Enrollment not found.');

            return self::FAILURE;
        }

        $type = strtolower((string) $this->option('type'));
        $force = (bool) $this->option('force');
        $sent = 0;
        $failed = 0;

        $dispatch = function (string $label, callable $callback) use (&$sent, &$failed): void {
            if ($callback()) {
                $this->info("Sent: {$label}");
                $sent++;
            } else {
                $this->warn("Skipped or failed: {$label}");
                $failed++;
            }
        };

        $sendDeposit = fn () => $dispatch('Deposit confirmation', fn () => $launchMail->sendDepositConfirmation($enrollment, $force));
        $sendBalanceDue = fn () => $dispatch('Balance due notice', fn () => $launchMail->sendBalanceDueNotice($enrollment, $force));
        $sendBalanceMid = fn () => $dispatch('Balance mid reminder', fn () => $launchMail->sendBalanceMidReminder($enrollment, $force));
        $sendBalanceFinal = fn () => $dispatch('Balance final reminder', fn () => $launchMail->sendBalanceFinalReminder($enrollment, $force));
        $sendBalancePaid = fn () => $dispatch('Balance paid confirmation', fn () => $launchMail->sendBalancePaidConfirmation($enrollment, $force));
        $sendWelcome = fn () => $dispatch('Welcome email', fn () => $welcomeMail->sendForEnrollment($enrollment, $force));
        $sendForfeit = fn () => $dispatch('Forfeit notice', fn () => $launchMail->sendForfeitNotice($enrollment, $force));

        $types = [
            'deposit' => $sendDeposit,
            'balance-due' => $sendBalanceDue,
            'balance-mid' => $sendBalanceMid,
            'balance-final' => $sendBalanceFinal,
            'balance-paid' => $sendBalancePaid,
            'welcome' => $sendWelcome,
            'forfeit' => $sendForfeit,
        ];

        if ($type === 'all') {
            foreach ($types as $sender) {
                $sender();
            }
        } elseif (isset($types[$type])) {
            $types[$type]();
        } else {
            $this->error('Unknown type. Use: deposit, balance-due, balance-mid, balance-final, balance-paid, welcome, forfeit, or all');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line("Done. Sent: {$sent}, skipped/failed: {$failed}.");
        $this->line('Recipient: '.($enrollment->user?->email ?? 'unknown'));

        if ($sent === 0) {
            $this->error('No emails were sent. Check SMTP settings and storage/logs/laravel.log.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
