<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LegalAgreementService;
use App\Services\SettingsService;
use App\Support\MailConfigurator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;

class DiagnoseLegalEmailCommand extends Command
{
    protected $signature = 'ssu:diagnose-legal-email
                            {email : User email address}
                            {--send : Actually send the legal agreement email}';

    protected $description = 'Diagnose why the Terms & NDA confirmation email is not being delivered';

    public function handle(SettingsService $settingsService, LegalAgreementService $legalAgreement): int
    {
        $email = strtolower($this->argument('email'));

        $this->info('SSU Academy — Legal email diagnostic');
        $this->newLine();

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email: {$email}");

            return self::FAILURE;
        }

        $this->line("User: {$user->name} <{$user->email}> (ID {$user->id})");
        $this->line('Legal accepted at: '.($user->legal_agreement_accepted_at?->toDateTimeString() ?? 'never'));
        $this->newLine();

        $this->line('<fg=cyan>1. Mail configuration</>');
        $setting = $settingsService->getSetting(['type' => 'smtp']);

        if (! $setting) {
            $this->error('  No SMTP settings row in database.');

            return self::FAILURE;
        }

        $fields = $setting->fields ?? [];

        if (! MailConfigurator::applyFromSetting($setting)) {
            $this->error('  Mail is not configured correctly.');
            $this->line('  Driver: '.($fields['mail_mailer'] ?? 'missing'));
            $this->line('  Fix in Admin → Settings → SMTP (try Resend API driver).');

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $this->info("  Mail driver: {$mailer}");
        $this->line('  From: '.config('mail.from.name').' <'.config('mail.from.address').'>');

        if ($mailer === 'smtp') {
            $this->line('  Host: '.config('mail.mailers.smtp.host'));
            $this->line('  Port: '.config('mail.mailers.smtp.port').' ('.(config('mail.mailers.smtp.encryption') ?: 'none').')');
        }

        $this->newLine();

        $this->line('<fg=cyan>2. CMS legal pages</>');
        $terms = $legalAgreement->getTermsPage();
        $nda = $legalAgreement->getNdaPage();

        $this->line('  Terms page: '.($terms ? "found (ID {$terms->id}, active=".($terms->active ? 'yes' : 'no').')' : 'MISSING'));
        $this->line('  NDA page: '.($nda ? "found (ID {$nda->id}, active=".($nda->active ? 'yes' : 'no').')' : 'MISSING'));

        if (! $terms || ! $nda) {
            $this->error('  Legal pages are missing or inactive. Email cannot be sent.');
            $this->line('  Ensure /terms-and-conditions and /non-disclosure-agreement exist and are active.');

            return self::FAILURE;
        }

        $this->newLine();

        $this->line('<fg=cyan>3. PDF generation</>');

        try {
            Pdf::loadView('mail.pdf.legal-document', [
                'title' => $terms->title,
                'html' => $terms->description ?? '',
            ])->output();

            $this->info('  PDF generation OK');
        } catch (\Throwable $exception) {
            $this->error('  PDF generation failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();

        if (! $this->option('send')) {
            $this->warn('Dry run only. Re-run with --send to deliver the legal agreement email.');
            $this->line('  php artisan ssu:diagnose-legal-email '.$email.' --send');

            return self::SUCCESS;
        }

        $this->line('<fg=cyan>4. Sending legal agreement email</>');

        try {
            $apiKey = is_array($fields) ? ($fields['mail_password'] ?? null) : null;
            $legalAgreement->deliverAcceptanceEmail(
                $user,
                resendApiKey: is_string($apiKey) ? $apiKey : null,
            );

            $this->info("  Legal agreement email sent to {$user->email}");
            $this->line('  Check inbox and spam. Also check the Resend dashboard for delivery status.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('  Send failed: '.$exception->getMessage());

            if ($mailer === 'smtp') {
                $this->newLine();
                $this->line('  SMTP appears blocked or misconfigured on this server.');
                $this->line('  Switch Admin → SMTP → Mail driver to "Resend API" and save your Resend API key.');
            }

            return self::FAILURE;
        }
    }
}
