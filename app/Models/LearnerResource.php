<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class LearnerResource extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'learner_resources';

    protected $fillable = [
        'type',
        'title',
        'description',
        'file',
        'file_name',
        'link',
    ];

    protected function file(): Attribute
    {
        return Attribute::make(get: fn (?string $value) => $value ? public_asset_url($value) : null);
    }
}
