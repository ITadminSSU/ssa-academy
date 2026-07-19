<?php

namespace App\Models\Course;

use App\Models\Course\SectionLesson;
use Illuminate\Database\Eloquent\Model;

class LessonResource extends Model
{
    protected $fillable = [
        'title',
        'type',
        'resource',
        'is_downloadable',
        'section_lesson_id',
    ];

    protected $casts = [
        'is_downloadable' => 'boolean',
    ];

    public function section_lesson()
    {
        return $this->belongsTo(SectionLesson::class);
    }
}
