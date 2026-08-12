<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TeamMember extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'name',
        'role',
        'photo',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected function photo(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if ($this->exists) {
                    $media = $this->getMedia('default')
                        ->first(fn ($item) => $item->getCustomProperty('name') === 'photo')
                        ?? $this->getMedia('*', ['name' => 'photo'])->first();

                    if ($media) {
                        return media_public_url($media);
                    }
                }

                return $value ? public_asset_url($value) : null;
            },
            set: fn (?string $value) => ['photo' => $value],
        );
    }
}
