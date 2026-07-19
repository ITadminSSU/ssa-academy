<?php

namespace App\Models\Course;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonActivitySubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'attachment_type',
        'attachment_path',
        'comment',
        'submitted_at',
        'marks_obtained',
        'instructor_feedback',
        'status',
        'attempt_number',
        'section_lesson_id',
        'user_id',
        'grader_id',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'marks_obtained' => 'decimal:2',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(SectionLesson::class, 'section_lesson_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'grader_id');
    }
}
