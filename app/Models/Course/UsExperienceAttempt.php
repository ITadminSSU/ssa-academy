<?php

namespace App\Models\Course;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsExperienceAttempt extends Model
{
    public const STATUS_PASSED = 'passed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'us_experience_plan_id',
        'user_id',
        'attempt_number',
        'takeoff_pdf_url',
        'takeoff_pdf_name',
        'boq_xlsx_url',
        'boq_xlsx_name',
        'answer_data',
        'marks_obtained',
        'lines_correct',
        'lines_total',
        'lines_percent',
        'grading_breakdown',
        'status',
        'submitted_at',
        'trainer_feedback',
    ];

    protected $casts = [
        'answer_data' => 'array',
        'grading_breakdown' => 'array',
        'submitted_at' => 'datetime',
        'attempt_number' => 'integer',
        'marks_obtained' => 'float',
        'lines_correct' => 'integer',
        'lines_total' => 'integer',
        'lines_percent' => 'float',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(UsExperiencePlan::class, 'us_experience_plan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPassed(): bool
    {
        return $this->status === self::STATUS_PASSED;
    }

    public function toStudentArray(bool $includeBreakdown = true): array
    {
        $payload = [
            'id' => $this->id,
            'attempt_number' => $this->attempt_number,
            'status' => $this->status,
            'marks_obtained' => $this->marks_obtained,
            'lines_correct' => $this->lines_correct,
            'lines_total' => $this->lines_total,
            'lines_percent' => $this->lines_percent,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'trainer_feedback' => $this->trainer_feedback,
            'takeoff_pdf_name' => $this->takeoff_pdf_name,
            'boq_xlsx_name' => $this->boq_xlsx_name,
        ];

        if ($includeBreakdown) {
            $payload['grading_breakdown'] = $this->grading_breakdown;
        }

        return $payload;
    }
}
