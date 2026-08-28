<?php

namespace App\Models\Course;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UsExperiencePlan extends Model
{
    protected $fillable = [
        'course_id',
        'group_name',
        'group_description',
        'title',
        'sort_order',
        'pass_mark',
        'max_attempts',
        'published',
        'drawings',
        'blank_template_file_url',
        'blank_template_file_name',
        'answer_key_file_url',
        'answer_key_file_name',
        'line_items',
        'parsed_at',
        'tutorial_video_url',
        'tutorial_video_name',
    ];

    protected $casts = [
        'drawings' => 'array',
        'line_items' => 'array',
        'published' => 'boolean',
        'parsed_at' => 'datetime',
        'pass_mark' => 'integer',
        'max_attempts' => 'integer',
        'sort_order' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(UsExperienceAttempt::class);
    }

    public function isReady(): bool
    {
        return $this->published
            && !empty($this->line_items)
            && filled($this->blank_template_file_url)
            && !empty($this->drawings);
    }

    public function drawingsList(): array
    {
        return array_values(array_filter(
            $this->drawings ?? [],
            fn ($drawing) => is_array($drawing) && !empty($drawing['file_url'])
        ));
    }

    /**
     * @return array<int, array{key: string, item: string, unit: string, expected_qty: float, tolerance_override?: float|null}>
     */
    public function answerKeyLines(): array
    {
        return array_values($this->line_items ?? []);
    }

    public function toTrainerArray(): array
    {
        return [
            ...$this->toArray(),
            'is_ready' => $this->isReady(),
            'drawings_count' => count($this->drawingsList()),
            'line_count' => count($this->answerKeyLines()),
        ];
    }

    public function toPublicTeaseArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'group_name' => $this->group_name,
            'group_description' => $this->group_description,
            'sort_order' => $this->sort_order,
        ];
    }
}
