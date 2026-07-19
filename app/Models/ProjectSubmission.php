<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ProjectSubmission extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'project_id',
        'user_id',
        'file',
        'file_name',
        'submitted_at',
        'score',
        'feedback',
        'scored_by',
        'scored_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'scored_at' => 'datetime',
        'score' => 'decimal:2',
    ];

    protected function file(): Attribute
    {
        return Attribute::make(get: fn (?string $value) => $value ? public_asset_url($value) : null);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scorer()
    {
        return $this->belongsTo(User::class, 'scored_by');
    }
}
