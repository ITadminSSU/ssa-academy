<?php

namespace App\Models;

use App\Enums\ScamTiplineStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ScamTiplineReport extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    protected $table = 'scam_tipline_reports';

    protected $fillable = [
        'reporter_name',
        'reporter_email',
        'link',
        'normalized_link',
        'normalized_link_hash',
        'details',
        'screenshot',
        'screenshot_name',
        'status',
        'public_note',
        'is_published',
        'confirmed_at',
        'duplicate_of_id',
        'ip_address',
        'user_agent',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'status' => ScamTiplineStatus::class,
        'is_published' => 'boolean',
        'confirmed_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected $appends = [
        'share_url',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withDefault();
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(ScamTiplineAudit::class, 'scam_tipline_report_id')->latest('id');
    }

    public function scopePublishedWarnings(Builder $query): Builder
    {
        return $query
            ->where('status', ScamTiplineStatus::Confirmed)
            ->where('is_published', true)
            ->whereNotNull('confirmed_at');
    }

    protected function screenshot(): Attribute
    {
        return Attribute::make(get: fn (?string $value) => $value ? public_asset_url($value) : null);
    }

    protected function shareUrl(): Attribute
    {
        return Attribute::make(get: function () {
            if ($this->status !== ScamTiplineStatus::Confirmed || ! $this->is_published) {
                return null;
            }

            return route('fraud-training-tipline.warning', $this);
        });
    }
}
