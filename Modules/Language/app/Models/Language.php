<?php

namespace Modules\Language\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $fillable = [
        'name',
        'code',
        'flag',
        'nativeName',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'is_default' => 'boolean',
    ];

    public function properties()
    {
        return $this->hasMany(LanguageProperty::class);
    }
}
