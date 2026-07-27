<?php

namespace App\Console\Commands;

use App\Jobs\SendEmailVerificationNotificationJob;
use App\Models\User;
use App\Services\LegalAgreementService;
use App\Services\SettingsService;
use App\Support\MailConfigurator;
use Illuminate\Console\Command;

class ResendRegistrationEmailsCommand extends Command
{
    protected $signature = 'ssu:resend-registration-emails
                            {email : The user email address}
                            {--verification : Resend the email verification link}
                            {--legal : Resend the Terms & NDA acceptance email}
                            {--all : Resend both emails}';

    protected $description = 'Resend registration emails after SMTP has been fixed';

    public function handle(SettingsService $settingsService, LegalAgreementService $legalAgreement): int
    {
        $user = User::query()->where('email', strtolower($this->argument('email')))->first();

        if (! $user) {
            $this->error('No user found with that email.');

            return self::FAILURE;
        }

        $setting = $settingsService->getSetting(['type' => 'smtp']);

        if (! MailConfigurator::applyFromSetting($setting)) {
            $this->error('Mail is not configured. Fix SMTP in Admin → Settings → SMTP.');

            return self::FAILURE;
        }

        $sendVerification = $this->option('all') || $this->option('verification');
        $sendLegal = $this->option('all') || $this->option('legal');

        if (! $sendVerification && ! $sendLegal) {
            $sendVerification = true;
            $sendLegal = true;
        }

        $this->info('Resending registration emails for '.$user->email);
        $this->newLine();

        if ($sendVerification) {
            if ($user->hasVerifiedEmail()) {
                $this->warn('  Verification: skipped (email already verified).');
            } else {
                SendEmailVerificationNotificationJob::dispatchSync($user->id, autoVerifyOnFailure: false);
                $user->refresh();

                if ($user->hasVerifiedEmail()) {
                    $this->error('  Verification: send failed and user was not verified. Check SMTP and logs.');
                } else {
                    $this->info('  Verification: sent.');
                }
            }
        }

        if ($sendLegal) {
            if (! $user->legal_agreement_accepted_at) {
                $this->warn('  Terms & NDA: skipped (no acceptance recorded for this user).');
            } else {
                try {
                    $apiKey = is_array($setting?->fields) ? ($setting->fields['mail_password'] ?? null) : null;
                    $legalAgreement->deliverAcceptanceEmail(
                        $user,
                        resendApiKey: is_string($apiKey) ? $apiKey : null,
                    );
                    $this->info('  Terms & NDA: sent with PDF attachments when possible.');
                } catch (\Throwable $exception) {
                    $this->error('  Terms & NDA: failed — '.$exception->getMessage());

                    return self::FAILURE;
                }
            }
        }

        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
