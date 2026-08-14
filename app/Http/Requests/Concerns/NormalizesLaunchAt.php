<?php

namespace App\Http\Requests\Concerns;

use Carbon\Carbon;

trait NormalizesLaunchAt
{
    protected function normalizeLaunchAtInput(): void
    {
        $this->merge([
            'launch_at' => $this->normalizeAppTimezoneDateTime($this->input('launch_at')),
        ]);
    }

    /**
     * Parse a datetime-local value as app timezone (e.g. Asia/Manila) for storage.
     */
    protected function normalizeAppTimezoneDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value, config('app.timezone'))->format('Y-m-d H:i:s');
    }
}
