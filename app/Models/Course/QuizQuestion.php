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
     * @return list<array<string, mixed>>
     */
    public function takeoffLineItems(): array
    {
        $items = $this->decodedOptions()['line_items'] ?? [];

        return is_array($items) ? array_values($items) : [];
    }

    public function hideTakeoffAnswerKey(): void
    {
        if (! $this->isTakeoff()) {
            return;
        }

        $options = $this->decodedOptions();
        $lineItems = $options['line_items'] ?? [];

        $this->setAttribute('takeoff', [
            'pdf_url' => $options['pdf_url'] ?? null,
            'pdf_name' => $options['pdf_name'] ?? null,
            'line_count' => is_array($lineItems) ? count($lineItems) : 0,
            'ready' => filled($options['pdf_url'] ?? null) && is_array($lineItems) && $lineItems !== [],
        ]);
        $this->setAttribute('options', json_encode([
            'pdf_url' => $options['pdf_url'] ?? null,
            'pdf_name' => $options['pdf_name'] ?? null,
        ]));
        $this->setAttribute('answer', json_encode([]));
    }
}
