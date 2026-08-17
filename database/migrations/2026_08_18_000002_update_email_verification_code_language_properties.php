<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Language\Models\Language;
use Modules\Language\Models\LanguageProperty;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('language_properties')) {
            return;
        }

        $this->overwriteKeys('auth', [
            'verify_title' => 'Verify your email',
            'verify_description' => 'Enter the 6-digit code we emailed you to continue to the student agreement and your dashboard.',
            'verification_sent' => 'A new verification code has been sent. Check your inbox and spam folder — the code is valid for 15 minutes.',
            'register_description' => 'External learners can register here. After you create an account, we email a 6-digit code — enter it on the next screen before the student agreement and dashboard.',
            'verification_link_sent' => 'A fresh verification code has been sent to your email address.',
        ]);

        $this->overwriteKeys('button', [
            'resend_verification_email' => 'Resend code',
        ]);

        Language::query()->pluck('code')->each(function (string $code): void {
            Cache::forget('language_properties:'.$code);
        });

        Cache::forget('language_properties');
    }

    public function down(): void
    {
        // Keep the code-based copy on rollback.
    }

    /**
     * @param  array<string, string>  $keys
     */
    private function overwriteKeys(string $group, array $keys): void
    {
        $properties = LanguageProperty::query()
            ->where('group', $group)
            ->get();

        foreach ($properties as $property) {
            $merged = $property->properties;

            if (! is_array($merged)) {
                continue;
            }

            $changed = false;

            foreach ($keys as $key => $value) {
                if (array_key_exists($key, $merged) && $merged[$key] !== $value) {
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
