<?php

namespace Modules\Exam\Services;

use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamAttemptAnswer;

class QuantityTakeoffAnalyticsService
{
    /**
     * @return array<int, array{key: string, item: string, unit: string, attempts: int, misses: int, miss_rate: float}>
     */
    public function getLineMissAnalytics(Exam $exam): array
    {
        if ($exam->exam_mode !== 'quantity_takeoff') {
            return [];
        }

        $question = $exam->questions()->where('question_type', 'quantity_takeoff')->first();

        if (!$question) {
            return [];
        }

        $answers = ExamAttemptAnswer::query()
            ->where('exam_question_id', $question->id)
            ->whereHas('exam_attempt', function ($query) use ($exam) {
                $query->where('exam_id', $exam->id)->where('status', 'completed');
            })
            ->get();

        $stats = [];

        foreach ($answers as $answer) {
            $breakdown = $answer->answer_data['grading_breakdown'] ?? [];

            foreach ($breakdown as $line) {
                $key = $line['key'] ?? null;

                if (!$key) {
                    continue;
                }

                if (!isset($stats[$key])) {
                    $stats[$key] = [
                        'key' => $key,
                        'item' => $line['item'] ?? '',
                        'unit' => $line['unit'] ?? '',
                        'attempts' => 0,
                        'misses' => 0,
                    ];
                }

                $stats[$key]['attempts']++;
                $isCorrect = (bool) ($line['is_correct'] ?? $line['within_tolerance'] ?? false);

                if (!$isCorrect) {
                    $stats[$key]['misses']++;
                }
            }
        }

        return collect($stats)
            ->map(function (array $stat) {
                $stat['miss_rate'] = $stat['attempts'] > 0
                    ? round(($stat['misses'] / $stat['attempts']) * 100, 1)
                    : 0;

                return $stat;
            })
            ->sortByDesc('miss_rate')
            ->values()
            ->all();
    }
}
