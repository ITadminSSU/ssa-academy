<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\SettingsService;
use App\Support\MailConfigurator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VerifyIntegrationsCommand extends Command
{
    protected $signature = 'ssu:verify-integrations
                            {--email= : Send a test email to this address}
                            {--smtp-only : Only check SMTP}
                            {--storage-only : Only check storage}';

    protected $description = 'Verify SMTP and R2/S3 storage settings saved in the admin panel';

    public function handle(SettingsService $settingsService): int
    {
        $smtpOnly = (bool) $this->option('smtp-only');
        $storageOnly = (bool) $this->option('storage-only');

        if ($smtpOnly && $storageOnly) {
            $this->error('Use only one of --smtp-only or --storage-only.');

            return self::FAILURE;
        }

        $runSmtp = ! $storageOnly;
        $runStorage = ! $smtpOnly;

        $this->info('SSU Academy integration check');
        $this->newLine();

        $smtpOk = true;
        $storageOk = true;

        if ($runSmtp) {
            $smtpOk = $this->verifySmtp($settingsService);
            $this->newLine();
        }

        if ($runStorage) {
            $storageOk = $this->verifyStorage($settingsService);
            $this->newLine();
        }

        if ($smtpOk && $storageOk) {
            $this->info('All checked integrations passed.');

            return self::SUCCESS;
        }

        $this->error('One or more integration checks failed. Review the messages above.');

        return self::FAILURE;
    }

    private function verifySmtp(SettingsService $settingsService): bool
    {
        $this->line('<fg=cyan>SMTP</>');

        $setting = $settingsService->getSetting(['type' => 'smtp']);

        if (! $setting instanceof Setting) {
            $this->error('  No SMTP settings row found in the database.');

            return false;
        }

        $fields = $setting->fields ?? [];

        if (! MailConfigurator::applyFromSetting($setting)) {
            $this->error('  Mail settings are incomplete or invalid.');
            $this->line('  Fix in Admin → Settings → SMTP');

            return false;
        }

        if (! filter_var($fields['mail_from_address'], FILTER_VALIDATE_EMAIL)) {
            $this->error('  From address is not a valid email.');

            return false;
        }

        $this->line('  Host: '.$fields['mail_host']);
        $this->line('  Port: '.$fields['mail_port'].' ('.($fields['mail_encryption'] ?: 'no encryption').')');
        $this->line('  From: '.$fields['mail_from_name'].' <'.$fields['mail_from_address'].'>');

        $recipient = $this->option('email') ?: $fields['mail_from_address'];

        try {
            Mail::raw(
                'This is a test email from '.config('app.name').'. If you received it, SMTP is working.',
                function ($message) use ($recipient) {
                    $message->to($recipient)->subject(config('app.name').' SMTP test');
                }
            );

            $this->info('  Test email sent to '.$recipient);

            return true;
        } catch (\Throwable $exception) {
            $this->error('  SMTP send failed: '.$exception->getMessage());
            $this->line('  Common fixes:');
            $this->line('    • Resend: host smtp.resend.com, port 587, encryption TLS, username resend');
            $this->line('    • Port 465 timeouts on cloud servers often mean the port is blocked — try 587 + TLS');
            $this->line('    • Port 587 → encryption TLS');
            $this->line('    • Port 465 → encryption SSL');
            $this->line('    • Gmail/Microsoft → use an app password, not your login password');
            $this->line('    • From address must be allowed/sender-verified with your provider');

            return false;
        }
    }

    private function verifyStorage(SettingsService $settingsService): bool
    {
        $this->line('<fg=cyan>Storage</>');

        $setting = $settingsService->getSetting(['type' => 'storage']);

        if (! $setting instanceof Setting) {
            $this->error('  No storage settings row found in the database.');

            return false;
        }

        $fields = $setting->fields ?? [];
        $driver = $fields['storage_driver'] ?? 'local';

        if ($driver !== 's3') {
            $this->warn('  Storage driver is "'.$driver.'" (local). CV uploads use server disk, not R2.');
            $this->line('  For production, set Admin → Settings → Storage → S3-Compatible (Cloudflare R2).');

            return true;
        }

        setStorageConfig($fields);

        $required = [
            'aws_access_key_id' => 'Access key ID',
            'aws_secret_access_key' => 'Secret access key',
            'aws_default_region' => 'Region',
            'aws_bucket' => 'Bucket',
            'aws_endpoint' => 'Endpoint',
        ];

        $missing = [];

        foreach ($required as $key => $label) {
            if (empty($fields[$key])) {
                $missing[] = $label;
            }
        }

        if ($missing !== []) {
            $this->error('  Missing: '.implode(', ', $missing));
            $this->line('  Fix in Admin → Settings → Storage');

            return false;
        }

        $this->line('  Bucket: '.$fields['aws_bucket']);
        $this->line('  Endpoint: '.$fields['aws_endpoint']);
        $this->line('  Path-style endpoint: '.(filter_var($fields['aws_use_path_style_endpoint'] ?? false, FILTER_VALIDATE_BOOL) ? 'yes' : 'no'));

        $path = 'healthchecks/'.Str::uuid().'.txt';

        try {
            Storage::disk('s3')->put($path, 'ssu-academy-storage-check');

            if (! Storage::disk('s3')->exists($path)) {
                throw new \RuntimeException('Upload succeeded but file could not be read back.');
            }

            Storage::disk('s3')->delete($path);

            $this->info('  R2/S3 upload, read, and delete succeeded.');

            return true;
        } catch (\Throwable $exception) {
            $this->error('  R2/S3 check failed: '.$exception->getMessage());
            $this->line('  Common fixes:');
            $this->line('    • Endpoint: https://<ACCOUNT_ID>.r2.cloudflarestorage.com');
            $this->line('    • Region: auto');
            $this->line('    • Enable "Use path style endpoint" for R2');
            $this->line('    • R2 API token needs Object Read & Write on the bucket');

            return false;
        }
    }
}
