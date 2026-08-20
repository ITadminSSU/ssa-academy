<?php

namespace App\Services;

use App\Models\DashboardWelcomeCampaign;
use App\Models\DashboardWelcomeDismissal;
use App\Models\User;
use App\Support\DashboardWelcomeOverlay;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardWelcomeCampaignService extends MediaService
{
    /**
     * @return array<string, mixed>|null
     */
    public function payloadForUser(User $user): ?array
    {
        $campaign = $this->selectForUser($user);

        return $campaign?->toPublicPayload();
    }

    public function selectForUser(User $user): ?DashboardWelcomeCampaign
    {
        $campaigns = DashboardWelcomeCampaign::query()
            ->where('enabled', true)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->filter(fn (DashboardWelcomeCampaign $campaign) => $campaign->isScheduledActive() && $campaign->hasDisplayContent())
            ->values();

        if ($campaigns->isEmpty()) {
            return null;
        }

        $dismissals = DashboardWelcomeDismissal::query()
            ->where('user_id', $user->id)
            ->whereIn('campaign_id', $campaigns->pluck('id'))
            ->get()
            ->keyBy('campaign_id');

        $eligible = $campaigns->filter(function (DashboardWelcomeCampaign $campaign) use ($dismissals) {
            if ($campaign->show_frequency === DashboardWelcomeCampaign::FREQUENCY_EVERY_HOME_VISIT) {
                return true;
            }

            $dismissal = $dismissals->get($campaign->id);

            if (! $dismissal) {
                return true;
            }

            return ! hash_equals((string) $dismissal->version, $campaign->contentVersion());
        })->values();

        if ($eligible->isEmpty()) {
            return null;
        }

        $topPriority = (int) $eligible->first()->priority;
        $pool = $eligible->where('priority', $topPriority)->values();

        return $this->pickWeighted($pool);
    }

    /**
     * @param  Collection<int, DashboardWelcomeCampaign>  $pool
     */
    private function pickWeighted(Collection $pool): ?DashboardWelcomeCampaign
    {
        if ($pool->isEmpty()) {
            return null;
        }

        if ($pool->count() === 1) {
            return $pool->first();
        }

        $total = (int) $pool->sum(fn (DashboardWelcomeCampaign $c) => max(1, (int) $c->weight));
        $roll = random_int(1, max(1, $total));
        $cursor = 0;

        foreach ($pool as $campaign) {
            $cursor += max(1, (int) $campaign->weight);
            if ($roll <= $cursor) {
                return $campaign;
            }
        }

        return $pool->first();
    }

    public function dismiss(User $user, int $campaignId, string $version): void
    {
        $version = trim($version);

        if ($campaignId < 1 || $version === '' || strlen($version) > 64) {
            return;
        }

        $campaign = DashboardWelcomeCampaign::query()->find($campaignId);

        if (! $campaign) {
            return;
        }

        // Session-only close for every_home_visit — do not persist.
        if ($campaign->show_frequency === DashboardWelcomeCampaign::FREQUENCY_EVERY_HOME_VISIT) {
            return;
        }

        DashboardWelcomeDismissal::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'campaign_id' => $campaignId,
            ],
            [
                'version' => $version,
                'dismissed_at' => now(),
            ],
        );
    }

    /**
     * @return Collection<int, DashboardWelcomeCampaign>
     */
    public function listForAdmin(): Collection
    {
        return DashboardWelcomeCampaign::query()
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get()
            ->map(function (DashboardWelcomeCampaign $campaign) {
                $campaign->setAttribute(
                    'poster_url',
                    DashboardWelcomeOverlay::resolvePosterUrl((string) ($campaign->poster_url ?? '')),
                );
                $campaign->setAttribute(
                    'video_url_resolved',
                    $campaign->video_type === DashboardWelcomeOverlay::VIDEO_FILE
                        ? DashboardWelcomeOverlay::resolvePosterUrl((string) ($campaign->video_url ?? ''))
                        : (string) ($campaign->video_url ?? ''),
                );
                $campaign->setAttribute('content_version', $campaign->contentVersion());
                $campaign->setAttribute('is_live_now', $campaign->enabled && $campaign->isScheduledActive() && $campaign->hasDisplayContent());

                return $campaign;
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DashboardWelcomeCampaign
    {
        return DB::transaction(function () use ($data) {
            $payload = $this->normalizePayload($data);
            $campaign = DashboardWelcomeCampaign::query()->create($payload);
            $this->applyMedia($campaign, $data);

            return $campaign->fresh() ?? $campaign;
        }, 5);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(DashboardWelcomeCampaign $campaign, array $data): DashboardWelcomeCampaign
    {
        return DB::transaction(function () use ($campaign, $data) {
            $payload = $this->normalizePayload($data, $campaign);
            $campaign->update($payload);
            $this->applyMedia($campaign->fresh() ?? $campaign, $data);

            return $campaign->fresh() ?? $campaign;
        }, 5);
    }

    public function delete(DashboardWelcomeCampaign $campaign): void
    {
        DB::transaction(function () use ($campaign) {
            foreach ($campaign->getMedia() as $media) {
                $media->delete();
            }
            $campaign->delete();
        }, 5);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data, ?DashboardWelcomeCampaign $existing = null): array
    {
        $videoType = (string) ($data['video_type'] ?? $existing?->video_type ?? DashboardWelcomeOverlay::VIDEO_NONE);
        if (! in_array($videoType, [
            DashboardWelcomeOverlay::VIDEO_NONE,
            DashboardWelcomeOverlay::VIDEO_FILE,
            DashboardWelcomeOverlay::VIDEO_EMBED,
        ], true)) {
            $videoType = DashboardWelcomeOverlay::VIDEO_NONE;
        }

        $videoUrl = trim((string) ($data['video_url'] ?? $existing?->video_url ?? ''));
        if (! empty($data['clear_video'])) {
            $videoUrl = '';
            $videoType = DashboardWelcomeOverlay::VIDEO_NONE;
        }

        if (! empty($data['new_video'])) {
            $videoType = DashboardWelcomeOverlay::VIDEO_FILE;
        }

        $posterUrl = trim((string) ($data['poster_url'] ?? $existing?->poster_url ?? ''));
        if (! empty($data['clear_poster'])) {
            $posterUrl = '';
        }

        $frequency = (string) ($data['show_frequency'] ?? $existing?->show_frequency ?? DashboardWelcomeCampaign::FREQUENCY_UNTIL_DISMISSED);
        if (! in_array($frequency, [
            DashboardWelcomeCampaign::FREQUENCY_UNTIL_DISMISSED,
            DashboardWelcomeCampaign::FREQUENCY_EVERY_HOME_VISIT,
        ], true)) {
            $frequency = DashboardWelcomeCampaign::FREQUENCY_UNTIL_DISMISSED;
        }

        if ($videoUrl === '' && empty($data['new_video'])) {
            $videoType = DashboardWelcomeOverlay::VIDEO_NONE;
        }

        return [
            'title' => trim((string) ($data['title'] ?? $existing?->title ?? 'Campaign')),
            'enabled' => filter_var($data['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'priority' => max(0, (int) ($data['priority'] ?? $existing?->priority ?? 10)),
            'weight' => max(1, (int) ($data['weight'] ?? $existing?->weight ?? 100)),
            'show_frequency' => $frequency,
            'starts_at' => $this->nullableDate($data['starts_at'] ?? null),
            'ends_at' => $this->nullableDate($data['ends_at'] ?? null),
            'headline' => trim((string) ($data['headline'] ?? '')),
            'body' => trim((string) ($data['body'] ?? '')),
            'cta_label' => trim((string) ($data['cta_label'] ?? '')),
            'cta_url' => trim((string) ($data['cta_url'] ?? '')),
            'poster_url' => $posterUrl,
            'video_type' => $videoType,
            'video_url' => $videoUrl,
            'autoplay_muted' => filter_var($data['autoplay_muted'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function nullableDate(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyMedia(DashboardWelcomeCampaign $campaign, array $data): void
    {
        if (! empty($data['clear_poster'])) {
            $prev = $campaign->getMedia('*', ['name' => 'dashboard_welcome_poster'])->first();
            $prev?->delete();
            if ($campaign->poster_url) {
                $campaign->forceFill(['poster_url' => ''])->save();
            }
        }

        if (! empty($data['new_poster']) && $data['new_poster'] instanceof UploadedFile) {
            $url = $this->addNewDeletePrev($campaign, $data['new_poster'], 'dashboard_welcome_poster');
            $campaign->forceFill(['poster_url' => $url])->save();
        }

        if (! empty($data['clear_video'])) {
            $prev = $campaign->getMedia('*', ['name' => 'dashboard_welcome_video'])->first();
            $prev?->delete();
            $campaign->forceFill([
                'video_url' => '',
                'video_type' => DashboardWelcomeOverlay::VIDEO_NONE,
            ])->save();
        }

        if (! empty($data['new_video']) && $data['new_video'] instanceof UploadedFile) {
            $url = $this->addNewDeletePrev($campaign, $data['new_video'], 'dashboard_welcome_video');
            $campaign->forceFill([
                'video_url' => $url,
                'video_type' => DashboardWelcomeOverlay::VIDEO_FILE,
            ])->save();
        }
    }
}
