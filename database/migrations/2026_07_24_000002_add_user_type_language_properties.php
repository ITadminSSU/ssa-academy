<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Language\Models\Language;
use Modules\Language\Models\LanguageProperty;

return new class extends Migration
{
    private array $inputKeys = [
        'user_type' => 'Learner Type',
        'user_type_placeholder' => 'Select learner type',
        'user_type_employee' => 'Employee (internal, free access)',
        'user_type_external' => 'External (public, may pay)',
    ];

    private array $dashboardKeys = [
        'update_user' => 'Update User',
        'select_user_type' => 'Select learner type',
        'select_approval_status' => 'Select the approval status',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('language_properties')) {
            return;
        }

        $this->mergeKeysIntoGroup('input', $this->inputKeys);
        $this->mergeKeysIntoGroup('dashboard', $this->dashboardKeys);

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
