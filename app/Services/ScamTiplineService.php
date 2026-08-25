<?php

namespace App\Services;

use App\Enums\ScamTiplineStatus;
use App\Enums\UserType;
use App\Mail\ScamTiplineReportMail;
use App\Models\ScamTiplineAudit;
use App\Models\ScamTiplineReport;
use App\Models\User;
use App\Support\TransactionalMailSender;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ScamTiplineService
{
    public function __construct(
        private MediaService $mediaService,
        private TransactionalMailSender $mailSender,
    ) {}

    public function normalizeLink(?string $link): ?string
    {
        if ($link === null) {
            return null;
        }

        $trimmed = trim($link);
        if ($trimmed === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $trimmed)) {
            $trimmed = 'https://'.$trimmed;
        }

        $parts = parse_url($trimmed);
        if ($parts === false || empty($parts['host'])) {
            return Str::lower($trimmed);
        }

        $host = Str::lower($parts['host']);
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        $path = $parts['path'] ?? '';
        $path = rtrim($path, '/') ?: '';

        $query = '';
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $params);
            foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', 'ref'] as $track) {
                unset($params[$track]);
            }
            if ($params !== []) {
                ksort($params);
                $query = '?'.http_build_query($params);
            }
        }

        return $host.$path.$query;
    }

    public function findLikelyDuplicate(?string $normalizedLink, ?int $excludeId = null): ?ScamTiplineReport
    {
        if (! $normalizedLink) {
            return null;
        }

        return ScamTiplineReport::query()
            ->where('normalized_link', $normalizedLink)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->whereIn('status', [
                ScamTiplineStatus::New->value,
                ScamTiplineStatus::Investigating->value,
                ScamTiplineStatus::Confirmed->value,
            ])
            ->latest('id')
            ->first();
    }

    /**
     * @param  array{reporter_name?: ?string, reporter_email?: ?string, link?: ?string, details?: ?string}  $data
     */
    public function submitPublicTip(array $data, Request $request, ?UploadedFile $screenshot = null): ScamTiplineReport
    {
        $normalized = $this->normalizeLink($data['link'] ?? null);
        $duplicate = $this->findLikelyDuplicate($normalized);

        $report = ScamTiplineReport::create([
            'reporter_name' => $this->nullableString($data['reporter_name'] ?? null),
            'reporter_email' => $this->nullableString($data['reporter_email'] ?? null),
            'link' => $this->nullableString($data['link'] ?? null),
            'normalized_link' => $normalized,
            'details' => $this->nullableString($data['details'] ?? null),
            'status' => ScamTiplineStatus::New,
            'duplicate_of_id' => $duplicate?->id,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ]);

        if ($screenshot) {
            $this->attachScreenshot($report, $screenshot);
        }

        $this->audit($report, null, 'submitted', null, ScamTiplineStatus::New->value, [
            'source' => 'public_form',
            'possible_duplicate_of' => $duplicate?->id,
        ]);

        $this->notifyStaff($report);

        return $report;
    }

    /**
     * @param  array{status?: string, public_note?: ?string, is_published?: bool, duplicate_of_id?: ?int}  $data
     */
    public function updateReport(ScamTiplineReport $report, array $data, User $actor): ScamTiplineReport
    {
        $fromStatus = $report->status?->value;
        $toStatus = $data['status'] ?? $fromStatus;

        $payload = [
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ];

        if (array_key_exists('public_note', $data)) {
            $payload['public_note'] = $this->nullableString($data['public_note']);
        }

        if (array_key_exists('duplicate_of_id', $data)) {
            $payload['duplicate_of_id'] = $data['duplicate_of_id'];
        }

        if (isset($data['status'])) {
            $payload['status'] = $data['status'];
            $status = ScamTiplineStatus::from($data['status']);

            if ($status === ScamTiplineStatus::Confirmed) {
                $payload['confirmed_at'] = $report->confirmed_at ?? now();
                $payload['is_published'] = array_key_exists('is_published', $data)
                    ? (bool) $data['is_published']
                    : true;
            } else {
                if (in_array($status, [ScamTiplineStatus::Dismissed, ScamTiplineStatus::Duplicate, ScamTiplineStatus::New], true)) {
                    $payload['is_published'] = false;
                } elseif (array_key_exists('is_published', $data)) {
                    $payload['is_published'] = (bool) $data['is_published'];
                }
            }
        } elseif (array_key_exists('is_published', $data)) {
            $payload['is_published'] = (bool) $data['is_published'];
        }

        $report->update($payload);
        $report->refresh();

        $this->audit(
            $report,
            $actor,
            $fromStatus !== $toStatus ? 'status_changed' : 'updated',
            $fromStatus,
            $toStatus,
            [
                'public_note' => $report->public_note,
                'is_published' => $report->is_published,
            ]
        );

        return $report;
    }

    public function softDelete(ScamTiplineReport $report, User $actor): void
    {
        $from = $report->status?->value;
        $report->update(['is_published' => false]);
        $report->delete();

        $this->audit($report, $actor, 'archived', $from, $from, []);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function audit(
        ScamTiplineReport $report,
        ?User $actor,
        string $action,
        ?string $fromStatus,
        ?string $toStatus,
        array $meta = [],
    ): void {
        ScamTiplineAudit::create([
            'scam_tipline_report_id' => $report->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'meta' => $meta,
        ]);
    }

    public function attachScreenshot(ScamTiplineReport $report, UploadedFile $file): void
    {
        $url = $this->mediaService->addNewDeletePrev($report, $file, 'screenshot');
        $report->update([
            'screenshot' => $url,
            'screenshot_name' => $file->getClientOriginalName(),
        ]);
    }

    private function notifyStaff(ScamTiplineReport $report): void
    {
        $recipients = User::query()
            ->whereIn('role', [UserType::ADMIN->value, UserType::SOCIAL_MEDIA->value])
            ->where('status', 1)
            ->get();

        foreach ($recipients as $user) {
            try {
                $this->mailSender->send(
                    $user,
                    new ScamTiplineReportMail($report),
                    'scam_tipline_new_report'
                );
            } catch (\Throwable $e) {
                Log::warning('scam_tipline_new_report notify failed', [
                    'user_id' => $user->id,
                    'report_id' => $report->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
