<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Project extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'project_category_id',
        'title',
        'description',
        'file',
        'file_name',
        'is_completed',
        'is_published',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'is_published' => 'boolean',
    ];

    protected function file(): Attribute
    {
        return Attribute::make(get: fn (?string $value) => $value ? public_asset_url($value) : null);
    }

    public function category()
    {
        return $this->belongsTo(ProjectCategory::class, 'project_category_id');
    }

    public function submissions()
    {
        return $this->hasMany(ProjectSubmission::class);
    }
}
