<?php

namespace App\Services\Integrations;

use App\Enums\EnrollmentAccessStatus;
use App\Models\Course\CourseEnrollment;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class GoHighLevelEnrollmentWebhook
{
    public const SETTING_TYPE = 'go_high_level';

    /**
     * @var list<string>
     */
    private const ALLOWED_HOSTS = [
        'services.leadconnectorhq.com',
        'services.msgsndr.com',
    ];

    public function setting(): Setting
    {
        return Setting::query()->firstOrCreate(
            ['type' => self::SETTING_TYPE],
            [
                'sub_type' => null,
                'title' => 'Go High Level',
                'fields' => [
                    'enabled' => false,
                    'webhook_url' => '',
                ],
            ],
        );
    }

    /**
     * Admin page data. The webhook URL itself is never included.
     *
     * @return array{id: int, enabled: bool, webhook_configured: bool, webhook_masked: string}
     */
    public function adminPayload(Setting $setting): array
    {
        $url = $this->storedUrl($setting);

        return [
            'id' => (int) $setting->getKey(),
            'enabled' => $this->isEnabled($setting),
            'webhook_configured' => $url !== '',
            'webhook_masked' => $this->mask($url),
        ];
    }

    public function update(Setting $setting, bool $enabled, ?string $webhookUrl): void
    {
        $fields = $this->fields($setting);
        $incoming = trim((string) $webhookUrl);

        if ($incoming !== '') {
            if (! $this->isAllowedWebhookUrl($incoming)) {
                throw ValidationException::withMessages([
                    'webhook_url' => 'Enter an HTTPS Go High Level inbound webhook URL.',
                ]);
            }

            $fields['webhook_url'] = $incoming;
        }

        $fields['enabled'] = $enabled;
        $setting->fields = $fields;
        $setting->save();
    }

    public function clear(Setting $setting): void
    {
        $fields = $this->fields($setting);
        $fields['webhook_url'] = '';
        $setting->fields = $fields;
        $setting->save();
    }

    public function queueForEnrollment(CourseEnrollment $enrollment): void
    {
        if (! $this->isReportable($enrollment)) {
            return;
        }

        $enrollmentId = (int) $enrollment->getKey();

        if ($enrollmentId <= 0) {
            return;
        }

        DB::afterCommit(function () use ($enrollmentId): void {
            dispatch(function () use ($enrollmentId): void {
                app(self::class)->send($enrollmentId);
            })->afterResponse();
        });
    }

    public function send(int $enrollmentId): void
    {
        try {
            $this->deliver($enrollmentId);
        } catch (\Throwable) {
            $this->logFailure($enrollmentId, null);
        }
    }

    public function isAllowedWebhookUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '' || preg_match('/\s/', $url) === 1 || str_contains($url, '@')) {
            return false;
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }

        if (isset($parts['port']) && (int) $parts['port'] !== 443) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        return in_array($host, self::ALLOWED_HOSTS, true);
    }

    public function mask(string $url): string
    {
        $url = trim($url);

        if ($url === '' || ! $this->isAllowedWebhookUrl($url)) {
            return '';
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $secret = trim($path, '/');
        $tail = strlen($secret) > 8 ? substr($secret, -4) : '';

        return 'https://'.$host.'/••••'.$tail;
    }

    private function deliver(int $enrollmentId): void
    {
        $config = $this->configuration();

        if (! $config['enabled'] || $config['webhook_url'] === '' || ! $this->isAllowedWebhookUrl($config['webhook_url'])) {
            return;
        }

        $enrollment = CourseEnrollment::query()->with(['user', 'course'])->find($enrollmentId);

        if (! $enrollment || ! $this->isReportable($enrollment)) {
            return;
        }

        $email = trim((string) ($enrollment->user?->email ?? ''));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        [$firstName, $lastName] = $this->splitName((string) ($enrollment->user?->name ?? ''));
        $enrolledAt = $enrollment->entry_date;

        $payload = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'course' => trim((string) ($enrollment->course?->title ?? '')),
            'courses' => $this->activeCourseTitles((int) $enrollment->user_id),
            'enrolled_at' => $enrolledAt?->toIso8601String() ?? '',
        ];

        $response = Http::timeout(5)
            ->connectTimeout(3)
            ->withoutRedirecting()
            ->withOptions(['http_errors' => false])
            ->asJson()
            ->post($config['webhook_url'], $payload);

        if (! $response->successful()) {
            $this->logFailure($enrollmentId, $response->status());
        }
    }

    /**
     * @return array{enabled: bool, webhook_url: string}
     */
    private function configuration(): array
    {
        if (! Schema::hasTable('settings')) {
            return ['enabled' => false, 'webhook_url' => ''];
        }

        $setting = Setting::query()->where('type', self::SETTING_TYPE)->first();

        if (! $setting) {
            return ['enabled' => false, 'webhook_url' => ''];
        }

        return [
            'enabled' => $this->isEnabled($setting),
            'webhook_url' => $this->storedUrl($setting),
        ];
    }

    private function isReportable(CourseEnrollment $enrollment): bool
    {
        $status = $enrollment->access_status;

        if ($status === null || $status === '') {
            return true;
        }

        if ($status instanceof EnrollmentAccessStatus) {
            return $status === EnrollmentAccessStatus::ACTIVE;
        }

        return (string) $status === EnrollmentAccessStatus::ACTIVE->value;
    }

    private function activeCourseTitles(int $userId): string
    {
        return CourseEnrollment::query()
            ->where('user_id', $userId)
            ->where(function ($query) {
                $query->where('access_status', EnrollmentAccessStatus::ACTIVE->value)
                    ->orWhereNull('access_status');
            })
            ->with('course:id,title')
            ->get()
            ->map(fn (CourseEnrollment $enrollment): string => trim((string) ($enrollment->course?->title ?? '')))
            ->filter(fn (string $title): bool => $title !== '')
            ->unique()
            ->sort(fn (string $left, string $right): int => strcasecmp($left, $right))
            ->values()
            ->implode(', ');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            return ['', ''];
        }

        $parts = explode(' ', $name, 2);

        return [trim($parts[0]), trim($parts[1] ?? '')];
    }

    private function isEnabled(Setting $setting): bool
    {
        return filter_var($this->fields($setting)['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function storedUrl(Setting $setting): string
    {
        return trim((string) ($this->fields($setting)['webhook_url'] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(Setting $setting): array
    {
        return is_array($setting->fields) ? $setting->fields : [];
    }

    private function logFailure(int $enrollmentId, ?int $status): void
    {
        Log::warning('Go High Level enrollment webhook failed', [
            'enrollment_id' => $enrollmentId,
            'http_status' => $status,
        ]);
    }
}
