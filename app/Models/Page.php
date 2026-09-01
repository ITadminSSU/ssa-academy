<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    use HasFactory;

    /**
     * Inner pages that the public site and footer still depend on.
     *
     * @var list<string>
     */
    public const PROTECTED_INNER_SLUGS = ['about-us', 'our-team', 'careers'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'type',
        'title',
        'banner',
        'favicon',
        'description',
        'meta_description',
        'meta_keywords',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Get all sections for this page
     */
    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class, 'page_id');
    }

    public function isProtectedInnerPage(): bool
    {
        return in_array($this->slug, self::PROTECTED_INNER_SLUGS, true);
    }
}
