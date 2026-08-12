<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Language\Models\Language;
use Modules\Language\Models\LanguageProperty;

return new class extends Migration
{
    private array $authKeys = [
        'two_factor_title' => 'Two-factor authentication',
        'two_factor_description' => 'Enter the 6-digit code from your authenticator app, or a recovery code.',
        'two_factor_recovery_hint' => 'You can also use one of your one-time recovery codes.',
        'two_factor_settings_description' => 'Add an authenticator app for an extra layer of security on admin and trainer accounts.',
        'two_factor_optional_note' => 'Optional for now. We recommend enabling it.',
        'two_factor_enabled' => 'Two-factor authentication is enabled',
        'two_factor_disabled' => 'Two-factor authentication is not enabled',
        'two_factor_scan_title' => 'Scan this QR code',
        'two_factor_scan_description' => 'Use Google Authenticator, Microsoft Authenticator, or Authy to scan the code, then enter the 6-digit code to confirm.',
        'two_factor_manual_secret' => 'Or enter this secret manually:',
        'two_factor_recovery_title' => 'Save your recovery codes',
        'two_factor_recovery_save' => 'Store these codes somewhere safe. Each code can be used once if you lose access to your authenticator.',
        'two_factor_regenerate_description' => 'Confirm your password and a current authenticator or recovery code to generate a new set of recovery codes.',
        'two_factor_disable_description' => 'Confirm your password and a current authenticator or recovery code to disable two-factor authentication.',
    ];

    private array $buttonKeys = [
        'two_factor_authentication' => 'Two-Factor Auth',
        'enable_two_factor' => 'Enable two-factor',
        'disable_two_factor' => 'Disable two-factor',
        'confirm_and_enable' => 'Confirm and enable',
        'regenerate_recovery_codes' => 'Regenerate recovery codes',
        'verify_and_continue' => 'Verify and continue',
    ];

    private array $inputKeys = [
        'two_factor_code' => 'Authentication code',
        'two_factor_code_placeholder' => '6-digit code or recovery code',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('language_properties')) {
            return;
        }

        $this->mergeKeysIntoGroup('auth', $this->authKeys);
        $this->mergeKeysIntoGroup('button', $this->buttonKeys);
        $this->mergeKeysIntoGroup('input', $this->inputKeys);

        Language::query()->pluck('code')->each(function (string $code): void {
            Cache::forget('language_properties:'.$code);
        });

        Cache::forget('language_properties');
    }

    public function down(): void
    {
        // Keep translation keys on rollback.
    }

    /**
     * @param  array<string, string>  $keys
     */
    private function mergeKeysIntoGroup(string $group, array $keys): void
    {
        $properties = LanguageProperty::query()
            ->where('group', $group)
            ->get();

        foreach ($properties as $property) {
            $merged = $property->properties;

            if (! is_array($merged)) {
                $merged = [];
            }

            $changed = false;

            foreach ($keys as $key => $value) {
                if (! array_key_exists($key, $merged) || $merged[$key] === '' || $merged[$key] === null) {
                    $merged[$key] = $value;
                    $changed = true;
                }
            }

            if ($changed) {
                $property->update(['properties' => $merged]);
            }
        }
    }
};
