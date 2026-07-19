<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfessionalDevelopmentGuide extends Model
{
    protected $fillable = [
        'key',
        'title',
        'content',
        'is_published',
        'sort',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];
}
