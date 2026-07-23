<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Language\Models\Language;
use Modules\Language\Models\LanguageProperty;

return new class extends Migration
{
    private array $dashboardKeys = [
        'update_admin' => 'Update Admin',
        'update_trainer' => 'Update Trainer',
        'edit_user' => 'Edit user',
        'delete_user' => 'Delete user',
        'view_trainer_profile' => 'View trainer profile',
        'manage_trainers' => 'Manage trainers',
        'email_readonly_note' => 'Email cannot be changed here.',
        'new_password_optional' => 'New password (optional)',
        'learner_type_help' => 'Internal employees get free course access. External learners may need to pay for public courses.',
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
