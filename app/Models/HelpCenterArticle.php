<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class HelpCenterArticle extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'help_center_articles';

    protected $fillable = [
        'user_id',
        'category',
        'title',
        'slug',
        'body',
        'video_url',
        'video',
        'video_name',
        'file',
        'file_name',
        'is_published',
        'sort_order',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (HelpCenterArticle $article) {
            if (empty($article->slug)) {
                $article->slug = static::generateUniqueSlug($article->title);
            }
        });

        static::updating(function (HelpCenterArticle $article) {
            if ($article->isDirty('title') && !$article->isDirty('slug')) {
                $article->slug = static::generateUniqueSlug($article->title);
            }
        });
    }

    public static function generateUniqueSlug(string $text): string
    {
        $slug = \Illuminate\Support\Str::slug($text);
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id')->withDefault();
    }

    protected function file(): Attribute
    {
        return Attribute::make(get: fn (?string $value) => $value ? public_asset_url($value) : null);
    }

    protected function video(): Attribute
    {
        return Attribute::make(get: fn (?string $value) => $value ? public_asset_url($value) : null);
    }
}
