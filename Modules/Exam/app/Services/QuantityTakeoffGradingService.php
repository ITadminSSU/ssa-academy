<?php

namespace Modules\Exam\Services;

class QuantityTakeoffGradingService
{
    /**
     * @param array<int, array{key: string, item: string, unit: string, expected_qty: float, tolerance_override?: float|null}> $answerKeyLines
     * @param array{quantities?: array<string, mixed>, line_overrides?: array<string, bool|null>} $answerData
     * @return array{
     *     marks_obtained: float,
     *     is_correct: bool,
     *     lines_correct: int,
     *     lines_total: int,
     *     lines_percent: float,
     *     grading_breakdown: array<int, array<string, mixed>>
     * }
     */
    public function grade(array $answerKeyLines, array $answerData, float $totalMarks): array
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
            $tolerance = $this->toleranceForLine($line, $expected, $line['unit']);
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
     * @param array{unit?: string, tolerance_override?: float|null} $line
     */
    private function toleranceForLine(array $line, float $expected, string $unit): float
    {
        if (isset($line['tolerance_override']) && $line['tolerance_override'] !== null && $line['tolerance_override'] !== '') {
            return max(0, (float) $line['tolerance_override']);
        }

        return $this->toleranceForUnit($unit, $expected);
    }

    private function toleranceForUnit(string $unit, float $expected): float
    {
        $unit = strtoupper(trim($unit));
        $percent = ((float) config('quantity_takeoff.tolerance_percent', 1)) / 100;
        $percentTolerance = abs($expected) * $percent;
        $floors = config('quantity_takeoff.unit_floors', []);
        $floor = (float) ($floors[$unit] ?? $floors['default'] ?? 1);

        return max($percentTolerance, $floor);
    }
}
