<?php

namespace App\Console\Commands;

use App\Services\SignWellService;
use Illuminate\Console\Command;

class DiagnoseSignWellCommand extends Command
{
    protected $signature = 'ssu:diagnose-signwell';

    protected $description = 'Check SignWell env config and verify the student agreement template via API';

    public function handle(SignWellService $signWell): int
    {
        $this->info('SignWell diagnosis');
        $this->newLine();

        $enabled = (bool) config('signwell.enabled');
        $apiKey = (string) config('signwell.api_key');
        $templateId = (string) config('signwell.template_id');
        $placeholder = (string) config('signwell.recipient_placeholder');
        $testMode = (bool) config('signwell.test_mode');

        $this->line('SIGNWELL_ENABLED: '.($enabled ? 'true' : 'false'));
        $this->line('SIGNWELL_API_KEY: '.(filled($apiKey) ? '(set, '.strlen($apiKey).' chars)' : '(missing)'));
        $this->line('SIGNWELL_TEMPLATE_ID: '.($templateId !== '' ? $templateId : '(missing)'));
        $this->line('SIGNWELL_RECIPIENT_PLACEHOLDER: '.($placeholder !== '' ? $placeholder : '(empty)'));
        $this->line('SIGNWELL_TEST_MODE: '.($testMode ? 'true' : 'false'));
        $this->line('isEnabled(): '.($signWell->isEnabled() ? 'yes' : 'no'));
        $this->newLine();

        if (! $signWell->isEnabled()) {
            $this->error('SignWell is not fully configured. Set SIGNWELL_ENABLED=true, SIGNWELL_API_KEY, and SIGNWELL_TEMPLATE_ID, then run php artisan config:clear.');

            return self::FAILURE;
        }

        $template = $signWell->getTemplate($templateId);

        if (! $template) {
            $this->error('Could not load template from SignWell. Check the API key and template ID.');

            return self::FAILURE;
        }

        $this->info('Template found: '.((string) ($template['name'] ?? $templateId)));
        $this->line('Template status: '.((string) ($template['status'] ?? 'unknown')));

        $placeholders = $signWell->templatePlaceholderNames();

        if ($placeholders === []) {
            $this->warn('No placeholders found on the template. Add a recipient placeholder in SignWell (e.g. Student).');

            return self::FAILURE;
        }

        $this->line('Template placeholders: '.implode(', ', $placeholders));

        $match = collect($placeholders)->first(fn (string $name) => strcasecmp($name, $placeholder) === 0);

        if ($match) {
            $this->info('Configured placeholder matches: '.$match);
        } else {
            $this->warn('Configured placeholder "'.$placeholder.'" does not match the template.');
            $this->line('Set SIGNWELL_RECIPIENT_PLACEHOLDER='.$placeholders[0].' (or rename the placeholder in SignWell to match).');
            $this->line('The app will auto-fall back to “'.$placeholders[0].'” when creating documents.');
        }

        $this->newLine();
        $this->info('SignWell looks reachable. Try Sign Student Agreement again after config:clear if you changed env.');

        return self::SUCCESS;
    }
}
