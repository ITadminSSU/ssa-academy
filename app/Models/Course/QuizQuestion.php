<?php

namespace App\Models\Course;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    use HasFactory;

    public const TYPE_TAKEOFF = 'quantity_takeoff';

    protected $fillable = [
        'title',
        'type',
        'answer',
        'options',
        'sort',
        'section_quiz_id',
    ];

    /**
     * Get the quiz that owns the question.
     */
    public function lesson_quiz()
    {
        return $this->belongsTo(SectionQuiz::class, 'section_quiz_id');
    }

    public function answers()
    {
        return $this->hasMany(QuestionAnswer::class);
    }

    public function isTakeoff(): bool
    {
        return $this->type === self::TYPE_TAKEOFF;
    }

    /**
     * @return array<string, mixed>
     */
    public function decodedOptions(): array
    {
        $options = $this->options;

        if (is_string($options)) {
            $decoded = json_decode($options, true);
            $options = is_array($decoded) ? $decoded : [];
        }

        return is_array($options) ? $options : [];
    }

    /**
     * @return list<array{file_url: string, file_name: string}>
     */
    public function takeoffDrawings(): array
    {
        $options = $this->decodedOptions();
        $drawings = $options['drawings'] ?? null;

        if (is_array($drawings)) {
            return array_values(array_filter(
                $drawings,
                fn ($drawing) => is_array($drawing) && filled($drawing['file_url'] ?? null)
            ));
        }

        if (filled($options['pdf_url'] ?? null)) {
            return [[
                'file_url' => (string) $options['pdf_url'],
                'file_name' => (string) ($options['pdf_name'] ?? 'plans.pdf'),
            ]];
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function takeoffLineItems(): array
    {
        $items = $this->decodedOptions()['line_items'] ?? [];

        return is_array($items) ? array_values($items) : [];
    }

    public function takeoffTolerancePercent(): float
    {
        $percent = $this->decodedOptions()['tolerance_percent'] ?? config('us_experience.default_tolerance_percent', 2);

        return min(100, max(0, (float) $percent));
    }

    public function hideTakeoffAnswerKey(): void
    {
        if (! $this->isTakeoff()) {
            return;
        }

        $options = $this->decodedOptions();
        $lineItems = $options['line_items'] ?? [];
        $drawings = $this->takeoffDrawings();
        $first = $drawings[0] ?? null;
        $hasAttempt = $this->relationLoaded('answers') && $this->answers->isNotEmpty();

        $this->setAttribute('takeoff', [
            'pdf_url' => $first['file_url'] ?? null,
            'pdf_name' => $first['file_name'] ?? null,
            'drawings' => array_map(
                fn (array $drawing) => ['file_name' => $drawing['file_name'] ?? 'plans.pdf'],
                $drawings
            ),
            'line_count' => is_array($lineItems) ? count($lineItems) : 0,
            'ready' => $drawings !== [] && is_array($lineItems) && $lineItems !== [],
            'tolerance_percent' => $this->takeoffTolerancePercent(),
            'tutorial_video' => ($hasAttempt && filled($options['tutorial_video_url'] ?? null))
                ? [
                    'url' => $options['tutorial_video_url'],
                    'name' => $options['tutorial_video_name'] ?? 'Walkthrough video',
                ]
                : null,
        ]);
        $this->setAttribute('options', json_encode([
            'pdf_url' => $first['file_url'] ?? null,
            'pdf_name' => $first['file_name'] ?? null,
            'tolerance_percent' => $this->takeoffTolerancePercent(),
        ]));
        $this->setAttribute('answer', json_encode([]));
    }
}
