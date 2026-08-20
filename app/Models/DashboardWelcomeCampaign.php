<?php

namespace App\Models;

use App\Support\DashboardWelcomeOverlay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class DashboardWelcomeCampaign extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const FREQUENCY_UNTIL_DISMISSED = 'until_dismissed';

    public const FREQUENCY_EVERY_HOME_VISIT = 'every_home_visit';

    protected $fillable = [
        'title',
        'enabled',
        'priority',
        'weight',
        'show_frequency',
        'starts_at',
        'ends_at',
        'headline',
        'body',
        'cta_label',
        'cta_url',
        'poster_url',
        'video_type',
        'video_url',
        'autoplay_muted',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'priority' => 'integer',
        'weight' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'autoplay_muted' => 'boolean',
    ];

    public function dismissals(): HasMany
    {
        return $this->hasMany(DashboardWelcomeDismissal::class, 'campaign_id');
    }

    public function isScheduledActive(?\DateTimeInterface $now = null): bool
    {
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public function contentVersion(): string
    {
        return DashboardWelcomeOverlay::version([
            'headline' => $this->headline ?? '',
            'body' => $this->body ?? '',
            'cta_label' => $this->cta_label ?? '',
            'cta_url' => $this->cta_url ?? '',
            'poster_url' => $this->poster_url ?? '',
            'video_type' => $this->video_type ?? DashboardWelcomeOverlay::VIDEO_NONE,
            'video_url' => $this->video_url ?? '',
            'autoplay_muted' => (bool) $this->autoplay_muted,
        ]);
    }

    public function hasDisplayContent(): bool
    {
        $videoType = $this->video_type ?? DashboardWelcomeOverlay::VIDEO_NONE;
        $videoUrl = trim((string) $this->video_url);

        return trim((string) $this->headline) !== ''
            || trim((string) $this->body) !== ''
            || trim((string) $this->poster_url) !== ''
            || trim((string) $this->cta_label) !== ''
            || ($videoType !== DashboardWelcomeOverlay::VIDEO_NONE && $videoUrl !== '');
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicPayload(): array
    {
        $videoType = $this->video_type ?? DashboardWelcomeOverlay::VIDEO_NONE;
        $videoUrl = trim((string) $this->video_url);

        if ($videoType === DashboardWelcomeOverlay::VIDEO_EMBED && $videoUrl !== '') {
            $videoUrl = DashboardWelcomeOverlay::withMutedAutoplayEmbedParams(
                $videoUrl,
                (bool) $this->autoplay_muted,
            );
        } elseif ($videoType === DashboardWelcomeOverlay::VIDEO_FILE && $videoUrl !== '') {
            $videoUrl = DashboardWelcomeOverlay::resolvePosterUrl($videoUrl);
        }

        return [
            'campaign_id' => $this->id,
            'version' => $this->contentVersion(),
            'headline' => (string) ($this->headline ?? ''),
            'body' => (string) ($this->body ?? ''),
            'cta_label' => (string) ($this->cta_label ?? ''),
            'cta_url' => trim((string) ($this->cta_url ?? '')) !== ''
                ? (string) $this->cta_url
                : '/dashboard/browse/all',
            'poster_url' => DashboardWelcomeOverlay::resolvePosterUrl((string) ($this->poster_url ?? '')),
            'video_type' => $videoType,
            'video_url' => $videoUrl,
            'autoplay_muted' => (bool) $this->autoplay_muted,
            'show_frequency' => $this->show_frequency ?: self::FREQUENCY_UNTIL_DISMISSED,
        ];
    }
}
