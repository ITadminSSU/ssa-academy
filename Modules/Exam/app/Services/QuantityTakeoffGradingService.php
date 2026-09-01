<?php

namespace Modules\Exam\Services;

class QuantityTakeoffGradingService
{
    /**
     * @param array<int, array{key: string, item: string, unit: string, expected_qty: float, tolerance_override?: float|null, tolerance_override_mode?: string|null}> $answerKeyLines
     * @param array{quantities?: array<string, mixed>, line_overrides?: array<string, bool|null>} $answerData
     * @param float|null $defaultAbsoluteTolerance When set (legacy), unused per-line overrides use this ± quantity band.
     * @param float|null $defaultPercentTolerance When set, unused per-line overrides use this percent of expected qty (no unit floors).
     * @return array{
     *     marks_obtained: float,
     *     is_correct: bool,
     *     lines_correct: int,
     *     lines_total: int,
     *     lines_percent: float,
     *     grading_breakdown: array<int, array<string, mixed>>
     * }
     */
    public function grade(array $answerKeyLines, array $answerData, float $totalMarks, ?float $defaultAbsoluteTolerance = null, ?float $defaultPercentTolerance = null): array
    {
        $submitted = collect($answerData['quantities'] ?? [])
            ->mapWithKeys(fn ($value, $key) => [(string) $key => $this->parseSubmittedValue($value)])
            ->all();

        $lineOverrides = $answerData['line_overrides'] ?? [];
        $breakdown = [];
        $correctCount = 0;

        foreach ($answerKeyLines as $line) {
            $key = $line['key'];
            $expected = (float) $line['expected_qty'];
            $submittedValue = $submitted[$key] ?? null;
            $resolved = $this->resolveTolerance($line, $expected, $line['unit'] ?? '', $defaultAbsoluteTolerance, $defaultPercentTolerance);
            $tolerance = $resolved['band'];
            $autoCorrect = $submittedValue !== null && abs($submittedValue - $expected) <= $tolerance;
            $finalCorrect = array_key_exists($key, $lineOverrides)
                ? (bool) $lineOverrides[$key]
                : $autoCorrect;

            if ($finalCorrect) {
                $correctCount++;
            }

            $breakdown[] = [
                'key' => $key,
                'item' => $line['item'],
                'unit' => $line['unit'],
                'expected_qty' => $expected,
                'submitted_qty' => $submittedValue,
                'auto_within_tolerance' => $autoCorrect,
                'within_tolerance' => $finalCorrect,
                'is_correct' => $finalCorrect,
                'tolerance' => $tolerance,
                'tolerance_percent' => $resolved['percent'],
                'manual_override' => array_key_exists($key, $lineOverrides) ? (bool) $lineOverrides[$key] : null,
            ];
        }

        $totalLines = count($answerKeyLines);
        $linePercent = $totalLines > 0 ? round(($correctCount / $totalLines) * 100, 2) : 0;
        $marksObtained = $totalLines > 0
            ? round(($correctCount / $totalLines) * $totalMarks, 2)
            : 0;

        return [
            'marks_obtained' => $marksObtained,
            'is_correct' => $correctCount === $totalLines,
            'lines_correct' => $correctCount,
            'lines_total' => $totalLines,
            'lines_percent' => $linePercent,
            'grading_breakdown' => $breakdown,
        ];
    }

    private function parseSubmittedValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = str_replace([',', ' '], '', (string) $value);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    /**
     * @param array{unit?: string, tolerance_override?: float|null, tolerance_override_mode?: string|null} $line
     * @return array{band: float, percent: float|null}
     */
    private function resolveTolerance(array $line, float $expected, string $unit, ?float $defaultAbsoluteTolerance, ?float $defaultPercentTolerance): array
    {
        if (isset($line['tolerance_override']) && $line['tolerance_override'] !== null && $line['tolerance_override'] !== '') {
            $override = max(0, (float) $line['tolerance_override']);
            $mode = (string) ($line['tolerance_override_mode'] ?? '');

            if ($mode === 'percent') {
                return [
                    'band' => abs($expected) * ($override / 100),
                    'percent' => $override,
                ];
            }

            return [
                'band' => $override,
                'percent' => null,
            ];
        }

        if ($defaultPercentTolerance !== null) {
            $percent = max(0, $defaultPercentTolerance);

            return [
                'band' => abs($expected) * ($percent / 100),
                'percent' => $percent,
            ];
        }

        if ($defaultAbsoluteTolerance !== null) {
            return [
                'band' => max(0, $defaultAbsoluteTolerance),
                'percent' => null,
            ];
        }

        return $this->toleranceForUnit($unit, $expected);
    }

    /**
     * @return array{band: float, percent: float|null}
     */
    private function toleranceForUnit(string $unit, float $expected): array
    {
        $unit = strtoupper(trim($unit));
        $percent = (float) config('quantity_takeoff.tolerance_percent', 1);
        $percentTolerance = abs($expected) * ($percent / 100);
        $floors = config('quantity_takeoff.unit_floors', []);
        $floor = (float) ($floors[$unit] ?? $floors['default'] ?? 1);
        $band = max($percentTolerance, $floor);

        return [
            'band' => $band,
            'percent' => $percentTolerance >= $floor ? $percent : null,
        ];
    }
}
