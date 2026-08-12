<?php

namespace App\Models;

use App\Support\S3CompatibleStorage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class PageSection extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'title',
        'description',
        'thumbnail',
        'flags',
        'properties',
        'active',
        'sort',
        'page_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'active' => 'boolean',
        'sort' => 'integer',
        'flags' => 'array',
        'properties' => 'array',
    ];

    /**
     * Get the page that owns this section
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id');
    }

    /**
     * Private R2/S3 object URLs cannot play in the browser — sign them on read.
     */
    protected function videoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => S3CompatibleStorage::resolvePlaybackUrl($value),
            set: fn (?string $value) => S3CompatibleStorage::normalizeStoredUrl($value),
        );
    }

    /**
     * Hero posters uploaded to R2 also need a signed URL when the bucket is private.
     */
    protected function thumbnail(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                $resolved = S3CompatibleStorage::resolvePlaybackUrl($value);

                if ($resolved === null || $resolved === '') {
                    return $resolved;
                }

                if (S3CompatibleStorage::isLocalPublicUrl($resolved) || ! str_starts_with($resolved, 'http')) {
                    return public_asset_url($resolved) ?? $resolved;
                }

                return $resolved;
            },
            set: function (?string $value) {
                if ($value === null || trim($value) === '') {
                    return null;
                }

                if (S3CompatibleStorage::isLocalPublicUrl($value)) {
                    return $value;
                }

                return S3CompatibleStorage::normalizeStoredUrl($value) ?? $value;
            },
        );
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($navbarItem) {
            if (is_null($navbarItem->sort)) {
                $navbarItem->sort = $navbarItem->getNextSortValue();
            }
        });
    }

    /**
     * Get the next sort value for this navbar item
     */
    protected function getNextSortValue(): int
    {
        $maxSort = static::query()->max('sort');

        return is_null($maxSort) ? 1 : (int) $maxSort + 1;
    }
}
