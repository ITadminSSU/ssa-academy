<?php

namespace App\Support;

use App\Services\SettingsService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class PefPackageSettings
{
    public const DEFAULT_DURATION = '2 years';

    /**
     * Same choices as the course expiry duration list.
     *
     * @var list<string>
     */
    public const DURATIONS = [
        '1 month',
        '2 months',
        '3 months',
        '4 months',
        '5 months',
        '6 months',
        '7 months',
        '8 months',
        '9 months',
        '10 months',
        '11 months',
        '12 months',
        '1 year',
        '2 years',
        '3 years',
        '4 years',
        '5 years',
        '6 years',
        '7 years',
        '8 years',
        '9 years',
        '10 years',
        '11 years',
        '12 years',
    ];

    public function __construct(private SettingsService $settings) {}

    public function enabled(): bool
    {
        return filter_var($this->fields()['pef_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return list<int>
     */
    public function courseIds(): array
    {
        $ids = $this->fields()['pef_course_ids'] ?? [];

        if (! is_array($ids)) {
            return [];
        }

        $normalized = [];

        foreach ($ids as $id) {
            $courseId = (int) $id;

            if ($courseId > 0) {
                $normalized[] = $courseId;
            }
        }

        return array_values(array_unique($normalized));
    }

    public function duration(): string
    {
        $duration = (string) ($this->fields()['pef_access_duration'] ?? self::DEFAULT_DURATION);

        return in_array($duration, self::DURATIONS, true) ? $duration : self::DEFAULT_DURATION;
    }

    public function isReady(): bool
    {
        return $this->enabled() && $this->courseIds() !== [];
    }

    public function endsAt(CarbonInterface $from): Carbon
    {
        $start = Carbon::instance($from);
        $duration = $this->duration();

        if (! preg_match('/^(\d+)\s+(month|months|year|years)$/i', $duration, $matches)) {
            return $start->copy()->addYears(2);
        }

        $value = (int) $matches[1];
        $unit = strtolower($matches[2]);

        if (str_contains($unit, 'month')) {
            return $start->copy()->addMonths($value);
        }

        return $start->copy()->addYears($value);
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(): array
    {
        $setting = $this->settings->getSetting(['type' => 'system']);
        $fields = $setting?->fields;

        return is_array($fields) ? $fields : [];
    }
}
