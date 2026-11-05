<?php

namespace App\Models\Course;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseLessonVideoCopy extends Model
{
    protected $fillable = [
        'course_id',
        'section_lesson_id',
        'source_bunny_video_id',
        'status',
        'error',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(SectionLesson::class, 'section_lesson_id');
    }
}
