<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Language\Models\Language;
use Modules\Language\Models\LanguageProperty;

return new class extends Migration
{
    private array $dashboardKeys = [
        'all_users' => 'All Users',
        'all_users_description' => 'Manage admins, internal employees, external learners, and trainers. The primary admin account is protected from deletion.',
        'primary_admin_badge' => 'Primary admin',
        'professional_type' => 'Professional Type',
        'cv_resume' => 'CV/Resume',
        'no_cv_uploaded' => 'No CV uploaded',
        'role_filter_all' => 'All users',
        'role_filter_admin' => 'Admin',
        'role_filter_internal_employee' => 'Internal employee',
        'role_filter_external' => 'External learner',
        'role_filter_trainer' => 'Trainer',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('language_properties')) {
            return;
        }

        $property = LanguageProperty::query()
            ->where('group', 'dashboard')
            ->orderBy('id')
            ->first();

        if (!$property) {
            return;
        }

        $properties = is_array($property->properties) ? $property->properties : [];
        $changed = false;

        foreach ($this->dashboardKeys as $key => $value) {
            if (!array_key_exists($key, $properties) || $properties[$key] === '' || $properties[$key] === null) {
                $properties[$key] = $value;
                $changed = true;
            }
        }

        if ($changed) {
            $property->update(['properties' => $properties]);
        }

        Language::query()->pluck('code')->each(function (string $code): void {
            Cache::forget('language_properties:' . $code);
        });

        Cache::forget('language_properties');
    }

    public function down(): void
    {
        // Keep translation keys on rollback.
    }
};
