<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Language\Models\Language;
use Modules\Language\Models\LanguageProperty;

return new class extends Migration
{
    private array $dashboardKeys = [
        'update_primary_admin' => 'Update Primary Admin',
        'primary_admin_edit_note' => 'Only the name and email can be updated for the primary admin. Status, password, and deletion remain protected.',
        'user_management' => 'User Management',
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
