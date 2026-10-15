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

        $this->replaceExact('auth', [
            'register_description' => [
                'External learners can register here. After you create an account, we email a 6-digit code — enter it on the next screen before the student agreement and dashboard.' => 'External learners can register here. After you create an account, we email a 6-digit code — enter it on the next screen, then you land on your dashboard.',
            ],
            'verify_description' => [
                'Enter the 6-digit code we emailed you to continue to the student agreement and your dashboard.' => 'Enter the 6-digit code we emailed you. After it is accepted, you land on your dashboard.',
            ],
            'register_required_fields_note' => [
                'All fields are required. You must upload a CV/resume, a government-issued ID, and agree to the Terms & Conditions and NDA before creating an account.' => 'Enter your full name, email, and password, then confirm the website terms to create your account.',
            ],
        ]);

        if (Schema::hasTable('languages')) {
            Language::query()->pluck('code')->each(function (string $code): void {
                Cache::forget('language_properties:'.$code);
            });
        }

        Cache::forget('language_properties');
    }

    public function down(): void
    {
        // Keep the updated signup copy.
    }

    /**
     * @param  array<string, array<string, string>>  $replacements
     */
    private function replaceExact(string $group, array $replacements): void
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

            foreach ($replacements as $key => $pairs) {
                $current = $merged[$key] ?? null;

                if (is_string($current) && isset($pairs[$current])) {
                    $merged[$key] = $pairs[$current];
                    $changed = true;
                }
            }

            if ($changed) {
                $property->update(['properties' => $merged]);
            }
        }
    }
};
