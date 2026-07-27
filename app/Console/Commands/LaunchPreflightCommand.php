<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\MailConfigurator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LaunchPreflightCommand extends Command
{
    protected $signature = 'ssu:launch-preflight';

    protected $description = 'Pre-launch checks for registration email delivery and queue processing';

    public function handle(SettingsService $settingsService): int
    {
        $this->info('SSU Academy — Launch preflight');
        $this->newLine();

        $checks = [
            $this->checkMail($settingsService),
            $this->checkQueue(),
            $this->checkLegalPages(),
            $this->checkResendPackage(),
        ];

        $this->newLine();

        if (in_array(false, $checks, true)) {
            $this->error('Launch preflight failed. Fix the issues above before go-live.');

            return self::FAILURE;
        }

        $this->info('Launch preflight passed. Registration emails are ready for high traffic.');

        return self::SUCCESS;
    }

    private function checkMail(SettingsService $settingsService): bool
    {
        $this->line('<fg=cyan>Mail delivery</>');

        $setting = $settingsService->getSetting(['type' => 'smtp']);

        if (! $setting instanceof Setting) {
            $this->error('  No mail settings found in Admin → SMTP.');

            return false;
        }

        $fields = $setting->fields ?? [];
        $driver = (string) ($fields['mail_mailer'] ?? 'smtp');

        if (! MailConfigurator::applyFromSetting($setting)) {
            $this->error('  Mail settings are incomplete.');

            return false;
        }

        if ($driver === 'resend') {
            $this->info('  Resend API driver configured (recommended for production).');
        } else {
            $this->warn('  SMTP driver is configured. For launch day, prefer Resend API if SMTP ports are blocked.');
            $this->line('  Host: '.($fields['mail_host'] ?? ''));
            $this->line('  Port: '.($fields['mail_port'] ?? '').' ('.($fields['mail_encryption'] ?: 'none').')');
        }

        $this->line('  From: '.($fields['mail_from_name'] ?? '').' <'.($fields['mail_from_address'] ?? '').'>');

        return true;
    }

    private function checkQueue(): bool
    {
        $this->newLine();
        $this->line('<fg=cyan>Registration email queue</>');

        $connection = (string) config('queue.default', 'sync');

        if ($connection === 'sync') {
            $this->warn('  QUEUE_CONNECTION=sync — emails run inline after signup.');
            $this->line('  This works for small traffic, but for launch day set QUEUE_CONNECTION=database and run a queue worker.');

            return true;
        }

        if ($connection !== 'database') {
            $this->warn("  QUEUE_CONNECTION={$connection} — ensure a worker is running for this connection.");

            return true;
        }

        $jobsTable = config('queue.connections.database.table', 'jobs');

        if (! Schema::hasTable($jobsTable)) {
            $this->error("  Queue table [{$jobsTable}] is missing. Run: php artisan migrate --force");

            return false;
        }

        $this->info("  Database queue configured (table: {$jobsTable}).");
        $this->line('  Forge → Site → Queue → enable worker: php artisan queue:work database --queue=mail,default --sleep=3 --tries=3 --max-time=3600');

        $pending = (int) DB::table($jobsTable)->where('queue', 'mail')->count();

        if ($pending > 0) {
            $this->warn("  {$pending} mail job(s) waiting — confirm the queue worker is running.");
        } else {
            $this->info('  No pending mail jobs in queue.');
        }

        $failedTable = config('queue.failed.table', 'failed_jobs');

        if (Schema::hasTable($failedTable)) {
            $failed = (int) DB::table($failedTable)->count();

            if ($failed > 0) {
                $this->warn("  {$failed} failed queue job(s) in {$failedTable}. Review with: php artisan queue:failed");
            }
        }

        return true;
    }

    private function checkLegalPages(): bool
    {
        $this->newLine();
        $this->line('<fg=cyan>Legal CMS pages</>');

        $terms = Page::query()->where('slug', 'terms-and-conditions')->first();
        $nda = Page::query()->where('slug', 'non-disclosure-agreement')->first();

        $ok = true;

        if (! $terms) {
            $this->error('  Missing page: terms-and-conditions');
            $ok = false;
        } else {
            $this->info('  Terms & Conditions page found.');
        }

        if (! $nda) {
            $this->error('  Missing page: non-disclosure-agreement');
            $ok = false;
        } else {
            $this->info('  NDA page found.');
        }

        return $ok;
    }

    private function checkResendPackage(): bool
    {
        $this->newLine();
        $this->line('<fg=cyan>Resend API package</>');

        if (interface_exists(\Psr\Http\Client\ClientInterface::class) && class_exists(\Resend\Client::class)) {
            $this->info('  resend/resend-php is installed.');

            return true;
        }

        $this->warn('  resend/resend-php is not installed on this server.');
        $this->line('  Run: composer install --no-dev');
        $this->line('  Required when mail driver is set to Resend API.');

        return (string) config('mail.default') !== 'resend';
    }
}
