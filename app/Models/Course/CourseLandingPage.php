<?php

namespace App\Models\Course;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CourseLandingPage extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'course_id',
        'countdown_ends_at',
        'hero_photo',
        'video_url',
        'video_source',
        'video_duration',
        'coach_line',
        'embed_html',
        'reviews',
    ];

    protected $casts = [
        'countdown_ends_at' => 'datetime',
        'reviews' => 'array',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
