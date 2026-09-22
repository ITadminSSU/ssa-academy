<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Language\Models\Language;
use Modules\Language\Models\LanguageProperty;

return new class extends Migration
{
    private array $copyByKey = [
        'delete_warning' => 'Are you sure you want to delete this?',
        'are_you_sure_to_delete' => 'Are you sure you want to delete this?',
        'delete_instructor_warning' => 'This trainer will be removed. Their courses will be reassigned to the admin. The person will remain as a student account; they will not be deleted.',
        'delete_user_warning' => 'This permanently deletes the account, including their CV/resume and government ID. Enrollments and related records will also be removed. This cannot be undone. This does not cancel Stripe subscriptions. Cancel billing in Stripe if they were paying for a course.',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('language_properties')) {
            return;
        }

        $userWarningAdded = false;

        LanguageProperty::query()->each(function (LanguageProperty $property) use (&$userWarningAdded): void {
            $properties = $property->properties;

            if (! is_array($properties)) {
                return;
            }

            $changed = false;

            foreach ($this->copyByKey as $key => $value) {
                if (! array_key_exists($key, $properties) && $key !== 'delete_user_warning') {
                    continue;
                }

                if ($key === 'delete_user_warning' && ! array_key_exists($key, $properties)) {
                    if ($property->group !== 'table' || ! array_key_exists('delete_instructor_warning', $properties)) {
                        continue;
                    }

                    $properties[$key] = $value;
                    $userWarningAdded = true;
                    $changed = true;

                    continue;
                }

                if (($properties[$key] ?? null) !== $value) {
                    $properties[$key] = $value;
                    $changed = true;

                    if ($key === 'delete_user_warning') {
                        $userWarningAdded = true;
                    }
                }
            }

            if ($changed) {
                $property->update(['properties' => $properties]);
            }
        });

        if (! $userWarningAdded) {
            $tableProperty = LanguageProperty::query()
                ->where('group', 'table')
                ->orderBy('id')
                ->first();

            if ($tableProperty) {
                $properties = is_array($tableProperty->properties) ? $tableProperty->properties : [];
                $properties['delete_user_warning'] = $this->copyByKey['delete_user_warning'];
                $tableProperty->update(['properties' => $properties]);
            }
        }

        $this->forgetLanguageCache();
    }

    public function down(): void
    {
        // Keep the corrected copy.
    }

    private function forgetLanguageCache(): void
    {
        if (class_exists(Language::class) && Schema::hasTable('languages')) {
            Language::query()->pluck('code')->each(function (string $code): void {
                Cache::forget('language_properties:'.$code);
            });
        }

        Cache::forget('language_properties');
    }
};
