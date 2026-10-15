<?php

namespace App\Models\Course;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsExperienceCertificate extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'group_name',
        'identifier',
        'certificate_id',
        'verification_reference',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
