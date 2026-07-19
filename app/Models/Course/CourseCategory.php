<?php

namespace App\Models\Course;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CourseCategory extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'title',
        'slug',
        'icon',
        'sort',
        'status',
        'show_in_nav',
        'description',
        'thumbnail',
    ];

    protected $casts = [
        'show_in_nav' => 'boolean',
    ];

    protected function thumbnail(): Attribute
    {
        return Attribute::make(get: fn (?string $value) => public_asset_url($value));
    }

    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    public function category_children()
    {
        return $this->hasMany(CourseCategoryChild::class);
    }
}
