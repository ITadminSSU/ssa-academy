<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Language\Models\Language;
use Modules\Language\Models\LanguageProperty;

return new class extends Migration
{
    private array $dashboardKeys = [
        'create_account' => 'Create Account',
        'account_type' => 'Account type',
        'account_type_admin' => 'Admin',
        'account_type_admin_description' => 'Full platform access',
        'account_type_employee' => 'Internal employee',
        'account_type_employee_description' => 'Student access with free internal courses',
        'account_type_trainer' => 'Trainer',
        'account_type_trainer_description' => 'Instructor access to manage courses',
        'create_account_note' => 'Email is marked verified and legal agreements are accepted automatically. Share the password securely with the user.',
    ];

    private array $buttonKeys = [
        'create_account' => 'Create Account',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('language_properties')) {
            return;
        }

        $this->mergeKeysIntoGroup('dashboard', $this->dashboardKeys);
        $this->mergeKeysIntoGroup('button', $this->buttonKeys);

        Language::query()->pluck('code')->each(function (string $code): void {
            Cache::forget('language_properties:' . $code);
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
        $property = LanguageProperty::query()
            ->where('group', $group)
            ->orderBy('id')
            ->first();

        if (!$property) {
            return;
        }

        $properties = $property->properties;

        if (!is_array($properties)) {
            $properties = [];
        }

        $changed = false;

        foreach ($keys as $key => $value) {
            if (!array_key_exists($key, $properties) || $properties[$key] === '' || $properties[$key] === null) {
                $properties[$key] = $value;
                $changed = true;
            }
        }

        if ($changed) {
            $property->update(['properties' => $properties]);
        }
    }
};
