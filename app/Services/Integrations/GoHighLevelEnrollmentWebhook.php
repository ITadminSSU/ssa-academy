<?php

namespace App\Services\Integrations;

use App\Enums\CourseBillingModel;
use App\Enums\EnrollmentAccessStatus;
use App\Enums\PaymentBillingType;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\Setting;
use App\Models\User;
use App\Support\EnrollmentListBilling;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\PaymentGateways\Models\PaymentHistory;

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
     * @return array{id: int, enabled: bool, webhook_configured: bool, webhook_masked: string, registration_webhook_configured: bool, registration_webhook_masked: string}
     */
    public function adminPayload(Setting $setting): array
    {
        $url = $this->storedUrl($setting);
        $registrationUrl = $this->storedRegistrationUrl($setting);

        return [
            'id' => (int) $setting->getKey(),
            'enabled' => $this->isEnabled($setting),
            'webhook_configured' => $url !== '',
            'webhook_masked' => $this->mask($url),
            'registration_webhook_configured' => $registrationUrl !== '',
            'registration_webhook_masked' => $this->mask($registrationUrl),
        ];
    }

    public function update(Setting $setting, bool $enabled, ?string $webhookUrl, ?string $registrationWebhookUrl = null): void
    {
        $fields = $this->fields($setting);
        $fields['webhook_url'] = $this->mergeUrl((string) ($fields['webhook_url'] ?? ''), $webhookUrl, 'webhook_url');

        if ($registrationWebhookUrl !== null) {
            $fields['registration_webhook_url'] = $this->mergeUrl(
                (string) ($fields['registration_webhook_url'] ?? ''),
                $registrationWebhookUrl,
                'registration_webhook_url',
            );
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

    public function clearRegistration(Setting $setting): void
    {
        $fields = $this->fields($setting);
        $fields['registration_webhook_url'] = '';
        $setting->fields = $fields;
        $setting->save();
    }

    public function queueForRegistration(User $user, ?Course $course = null, ?string $source = null): void
    {
        $userId = (int) $user->getKey();

        if ($userId <= 0) {
            return;
        }

        $courseId = $course ? (int) $course->getKey() : null;
        $source = $courseId ? null : $this->registrationSource($source);

        DB::afterCommit(function () use ($userId, $courseId, $source): void {
            dispatch(function () use ($userId, $courseId, $source): void {
                app(self::class)->sendRegistration($userId, $courseId, $source);
            })->afterResponse();
        });
    }

    public function sendRegistration(int $userId, ?int $courseId = null, ?string $source = null): void
    {
        try {
            $this->deliverRegistration($userId, $courseId, $source);
        } catch (\Throwable) {
            $this->logRegistrationFailure($userId, null);
        }
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

        $user = $enrollment->user;
        [$firstName, $lastName] = \App\Support\PersonName::webhookNames(
            $user?->given_name,
            $user?->last_name,
            $user?->name,
        );
        $enrolledAt = $enrollment->entry_date;

        $payment = $this->creatingPayment($enrollment);

        if ($this->shouldWaitForCreatingPayment($enrollment, $payment) || $this->alreadySent($enrollmentId)) {
            return;
        }

        $courseTitle = trim((string) ($enrollment->course?->title ?? ''));
        $courseId = (string) $enrollment->course_id;

        $payload = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'source_course' => $courseTitle,
            'source_course_id' => $courseId,
            'payment_status' => 'paid',
            'course' => $courseTitle,
            'courses' => $this->activeCourseTitles((int) $enrollment->user_id),
            'enrolled_at' => $enrolledAt?->toIso8601String() ?? '',
            'course_id' => $courseId,
            'payment_plan' => $this->paymentPlan($enrollment->course, $payment),
            'coupon' => EnrollmentListBilling::couponCodeFromPayment($payment),
            'amount' => $this->paidAmount($payment),
        ];

        $response = Http::timeout(5)
            ->connectTimeout(3)
            ->withoutRedirecting()
            ->withOptions(['http_errors' => false])
            ->asJson()
            ->post($config['webhook_url'], $payload);

        if (! $response->successful()) {
            $this->logFailure($enrollmentId, $response->status());

            return;
        }

        Cache::put($this->sentKey($enrollmentId), true, now()->addDays(7));
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

    private function isEnabled(Setting $setting): bool
    {
        return filter_var($this->fields($setting)['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function registrationSource(?string $source): ?string
    {
        return strtolower(trim((string) $source)) === 'evt' ? 'evt' : null;
    }

    private function deliverRegistration(int $userId, ?int $courseId, ?string $source = null): void
    {
        $config = $this->registrationConfiguration();

        if (! $config['enabled'] || $config['webhook_url'] === '' || ! $this->isAllowedWebhookUrl($config['webhook_url'])) {
            return;
        }

        $user = User::query()->find($userId);

        if (! $user) {
            return;
        }

        $email = trim((string) $user->email);

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        [$firstName, $lastName] = \App\Support\PersonName::webhookNames(
            $user->given_name,
            $user->last_name,
            $user->name,
        );
        $course = $courseId ? Course::query()->find($courseId) : null;
        $evt = ! $course && ! $courseId && $this->registrationSource($source) === 'evt';

        $payload = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'registered_at' => $user->created_at?->toIso8601String() ?? '',
            'source_course' => $course ? trim((string) $course->title) : ($evt ? 'EVT' : ''),
            'source_course_id' => $course ? (string) $course->id : ($evt ? 'evt' : ''),
        ];

        $response = Http::timeout(5)
            ->connectTimeout(3)
            ->withoutRedirecting()
            ->withOptions(['http_errors' => false])
            ->asJson()
            ->post($config['webhook_url'], $payload);

        if (! $response->successful()) {
            $this->logRegistrationFailure($userId, $response->status());
        }
    }

    private function creatingPayment(CourseEnrollment $enrollment): ?PaymentHistory
    {
        if (! Schema::hasTable('payment_histories')) {
            return null;
        }

        return PaymentHistory::query()
            ->where('user_id', $enrollment->user_id)
            ->where('purchase_id', $enrollment->course_id)
            ->where('purchase_type', Course::class)
            ->where(function ($query) {
                $query->whereNull('billing_type')
                    ->orWhere('billing_type', '!=', PaymentBillingType::SUBSCRIPTION_RENEWAL->value);
            })
            ->orderBy('id')
            ->first();
    }

    private function shouldWaitForCreatingPayment(CourseEnrollment $enrollment, ?PaymentHistory $payment): bool
    {
        if ($payment) {
            return false;
        }

        return $enrollment->enrollment_type === 'subscription' || filled($enrollment->subscription_id);
    }

    private function alreadySent(int $enrollmentId): bool
    {
        return Cache::has($this->sentKey($enrollmentId));
    }

    private function sentKey(int $enrollmentId): string
    {
        return 'ghl-enrollment-webhook:'.$enrollmentId;
    }

    private function paymentPlan(?Course $course, ?PaymentHistory $payment): string
    {
        $months = (int) ($course?->installment_months ?? 0);
        $billing = $payment?->billing_type;
        $billingValue = $billing instanceof PaymentBillingType ? $billing->value : (string) $billing;
        $model = $course?->billing_model;
        $installmentCourse = $model === CourseBillingModel::ONE_TIME_OR_INSTALLMENT
            || $model === CourseBillingModel::INSTALLMENT_SUBSCRIPTION;

        if ($installmentCourse && $months >= 1 && $billingValue === PaymentBillingType::SUBSCRIPTION->value) {
            return 'installment_'.$months;
        }

        return 'one_time';
    }

    private function paidAmount(?PaymentHistory $payment): string
    {
        if (! $payment || $payment->amount === null || $payment->amount === '') {
            return '';
        }

        return number_format((float) $payment->amount, 2, '.', '');
    }

    private function mergeUrl(string $current, ?string $incoming, string $errorKey): string
    {
        $incoming = trim((string) $incoming);

        if ($incoming === '') {
            return $current;
        }

        if (! $this->isAllowedWebhookUrl($incoming)) {
            throw ValidationException::withMessages([
                $errorKey => 'Enter an HTTPS Go High Level inbound webhook URL.',
            ]);
        }

        return $incoming;
    }

    /**
     * @return array{enabled: bool, webhook_url: string}
     */
    private function registrationConfiguration(): array
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
            'webhook_url' => $this->storedRegistrationUrl($setting),
        ];
    }

    private function storedUrl(Setting $setting): string
    {
        return trim((string) ($this->fields($setting)['webhook_url'] ?? ''));
    }

    private function storedRegistrationUrl(Setting $setting): string
    {
        return trim((string) ($this->fields($setting)['registration_webhook_url'] ?? ''));
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

    private function logRegistrationFailure(int $userId, ?int $status): void
    {
        Log::warning('Go High Level registration webhook failed', [
            'user_id' => $userId,
            'http_status' => $status,
        ]);
    }
}
