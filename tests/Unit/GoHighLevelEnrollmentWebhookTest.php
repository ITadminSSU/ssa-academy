<?php

use App\Enums\EnrollmentAccessStatus;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\Setting;
use App\Models\User;
use App\Services\Chat\ChatService;
use App\Services\Course\CompanionCourseEnrollmentService;
use App\Services\Course\CourseEnrollmentService;
use App\Services\Course\CourseEnrollmentWelcomeMailService;
use App\Services\Integrations\GoHighLevelEnrollmentWebhook;
use App\Services\Payment\LaunchOfferEnrollmentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\PaymentGateways\Models\PaymentHistory;

const GHL_WEBHOOK_URL = 'https://services.leadconnectorhq.com/hooks/secret-token-ab12';

beforeEach(function () {
    Schema::disableForeignKeyConstraints();

    foreach ([
        'payment_histories',
        'course_enrollments',
        'section_quizzes',
        'section_lessons',
        'courses',
        'settings',
        'users',
    ] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('role')->nullable();
        $table->timestamp('legal_agreement_accepted_at')->nullable();
        $table->timestamps();
    });

    Schema::create('settings', function (Blueprint $table) {
        $table->id();
        $table->string('type');
        $table->string('sub_type')->nullable();
        $table->string('title')->nullable();
        $table->json('fields')->nullable();
        $table->timestamps();
    });

    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('slug')->nullable();
        $table->string('status')->nullable();
        $table->timestamp('launch_at')->nullable();
        $table->string('expiry_type')->nullable();
        $table->string('expiry_duration')->nullable();
        $table->string('billing_model')->nullable();
        $table->unsignedSmallInteger('installment_months')->nullable();
        $table->timestamps();
    });

    Schema::create('course_enrollments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->string('enrollment_type')->nullable();
        $table->string('access_status')->nullable();
        $table->timestamp('entry_date')->nullable();
        $table->timestamp('expiry_date')->nullable();
        $table->timestamp('balance_paid_at')->nullable();
        $table->unsignedBigInteger('balance_payment_history_id')->nullable();
        $table->timestamp('deposit_paid_at')->nullable();
        $table->timestamp('forfeited_at')->nullable();
        $table->timestamp('welcome_email_sent_at')->nullable();
        $table->timestamps();
    });

    Schema::create('section_lessons', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('course_id');
        $table->timestamps();
    });

    Schema::create('section_quizzes', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('course_id');
        $table->timestamps();
    });

    Schema::create('payment_histories', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('transaction_id')->nullable();
        $table->string('coupon')->nullable();
        $table->json('meta')->nullable();
        $table->decimal('amount', 10, 2)->nullable();
        $table->string('billing_type')->nullable();
        $table->string('purchase_type')->nullable();
        $table->unsignedBigInteger('purchase_id')->nullable();
        $table->string('refund_status')->nullable();
        $table->timestamps();
    });

    $chat = Mockery::mock(ChatService::class);
    $chat->shouldReceive('syncEnrollmentAccess')->zeroOrMoreTimes();
    app()->instance(ChatService::class, $chat);

    $companion = Mockery::mock(CompanionCourseEnrollmentService::class);
    $companion->shouldReceive('grantForEnrollment')->zeroOrMoreTimes()->andReturnNull();
    app()->instance(CompanionCourseEnrollmentService::class, $companion);

    Http::preventStrayRequests();
});

afterEach(function () {
    Carbon\Carbon::setTestNow();
});

test('a disabled webhook sends nothing', function () {
    saveGoHighLevel(false, GHL_WEBHOOK_URL);
    Http::fake();

    enrollLearner('Jane Doe', 'jane@example.com', 'Alpha Takeoff');
    app()->terminate();

    Http::assertNothingSent();
});

test('an active enrollment posts only the allowed keys', function () {
    Carbon\Carbon::setTestNow('2026-10-23 07:46:00');
    saveGoHighLevel(true, GHL_WEBHOOK_URL);
    Http::fake([
        'https://services.leadconnectorhq.com/*' => Http::response(['ok' => true], 200),
    ]);

    $enrollment = enrollLearner('Jane Ann Doe', 'jane@example.com', 'Alpha Takeoff');
    Http::assertNothingSent();
    app()->terminate();

    Http::assertSentCount(1);
    Http::assertSent(function ($request) use ($enrollment) {
        $body = $request->data();

        expect($request->url())->toBe(GHL_WEBHOOK_URL)
            ->and($request->method())->toBe('POST')
            ->and(array_keys($body))->toBe([
                'first_name',
                'last_name',
                'email',
                'source_course',
                'source_course_id',
                'payment_status',
                'course',
                'courses',
                'enrolled_at',
                'course_id',
                'payment_plan',
                'coupon',
                'amount',
            ])
            ->and($body)->toBe([
                'first_name' => 'Jane',
                'last_name' => 'Ann Doe',
                'email' => 'jane@example.com',
                'source_course' => 'Alpha Takeoff',
                'source_course_id' => (string) $enrollment->course_id,
                'payment_status' => 'paid',
                'course' => 'Alpha Takeoff',
                'courses' => 'Alpha Takeoff',
                'enrolled_at' => $enrollment->fresh()->entry_date->toIso8601String(),
                'course_id' => (string) $enrollment->course_id,
                'payment_plan' => 'one_time',
                'coupon' => '',
                'amount' => '',
            ]);

        return true;
    });
});

test('go high level uses the stored given and last name', function () {
    Schema::table('users', function (Blueprint $table) {
        $table->string('given_name')->nullable();
        $table->string('last_name')->nullable();
    });

    saveGoHighLevel(true, GHL_WEBHOOK_URL);
    Http::fake();

    $user = User::query()->create([
        'name' => 'Jane Ann Doe',
        'given_name' => 'Janet',
        'last_name' => 'Doe',
        'email' => 'janet@example.com',
        'password' => 'secret',
        'role' => 'student',
    ]);

    enrollLearner('Jane Ann Doe', 'janet@example.com', 'Alpha Takeoff', null, 'free', false, $user);
    app()->terminate();

    Http::assertSent(function ($request) {
        expect($request->data()['first_name'])->toBe('Janet')
            ->and($request->data()['last_name'])->toBe('Doe');

        return true;
    });
});

test('a reserved enrollment sends nothing', function () {
    saveGoHighLevel(true, GHL_WEBHOOK_URL);
    Http::fake();

    enrollLearner('Jane Doe', 'jane@example.com', 'Alpha Takeoff', EnrollmentAccessStatus::RESERVED->value, 'deposit');
    app()->terminate();

    Http::assertNothingSent();
    expect(CourseEnrollment::query()->count())->toBe(1);
});

test('a second active course sends one new request with every active title', function () {
    saveGoHighLevel(true, GHL_WEBHOOK_URL);
    Http::fake([
        'https://services.leadconnectorhq.com/*' => Http::response('', 200),
    ]);

    $user = learner('Jane Doe', 'jane@example.com');
    enrollLearner('Jane Doe', 'jane@example.com', 'Zulu Estimating', user: $user);
    enrollLearner('Jane Doe', 'jane@example.com', 'Alpha Takeoff', user: $user);
    app()->terminate();

    Http::assertSentCount(2);

    $courses = [];
    Http::recorded()->each(function ($pair) use (&$courses) {
        $courses[] = $pair[0]->data()['course'];
        expect($pair[0]->data()['courses'])->toBe('Alpha Takeoff, Zulu Estimating')
            ->and(array_keys($pair[0]->data()))->toBe([
                'first_name',
                'last_name',
                'email',
                'source_course',
                'source_course_id',
                'payment_status',
                'course',
                'courses',
                'enrolled_at',
                'course_id',
                'payment_plan',
                'coupon',
                'amount',
            ]);
    });

    expect($courses)->toEqualCanonicalizing(['Zulu Estimating', 'Alpha Takeoff']);
});

test('paying the launch-offer balance sends one webhook', function () {
    saveGoHighLevel(true, GHL_WEBHOOK_URL);
    Http::fake([
        'https://services.leadconnectorhq.com/*' => Http::response('', 200),
    ]);

    $mail = Mockery::mock(CourseEnrollmentWelcomeMailService::class);
    $mail->shouldReceive('sendForEnrollment')->once()->andReturn(true);
    app()->instance(CourseEnrollmentWelcomeMailService::class, $mail);

    $user = learner('Jane Doe', 'jane@example.com');
    $course = course('Alpha Takeoff');
    CourseEnrollment::query()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'enrollment_type' => 'deposit',
        'access_status' => EnrollmentAccessStatus::RESERVED->value,
        'entry_date' => now(),
    ]);
    PaymentHistory::query()->create([
        'user_id' => $user->id,
        'transaction_id' => 'txn_balance',
        'amount' => 50,
    ]);

    app(LaunchOfferEnrollmentService::class)->recordBalancePayment(
        $user,
        $course,
        'stripe',
        'txn_balance',
        50,
    );
    app()->terminate();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->data()['course'] === 'Alpha Takeoff'
        && $request->data()['email'] === 'jane@example.com');
    expect(CourseEnrollment::query()->first()->access_status)->toBe(EnrollmentAccessStatus::ACTIVE);
});

test('a non-allowlisted webhook url is rejected and sends nothing', function () {
    $setting = saveGoHighLevel(true, GHL_WEBHOOK_URL);
    $webhook = app(GoHighLevelEnrollmentWebhook::class);

    expect(fn () => $webhook->update($setting, true, 'https://evil.example/hooks/steal'))
        ->toThrow(ValidationException::class);

    $setting->refresh();
    expect($setting->fields['webhook_url'])->toBe(GHL_WEBHOOK_URL);

    $setting->update([
        'fields' => [
            'enabled' => true,
            'webhook_url' => 'http://services.leadconnectorhq.com/hooks/plain',
        ],
    ]);

    Http::fake();
    enrollLearner('Jane Doe', 'jane@example.com', 'Alpha Takeoff');
    app()->terminate();

    Http::assertNothingSent();

    $payload = $webhook->adminPayload(saveGoHighLevel(true, GHL_WEBHOOK_URL));

    expect(json_encode($payload))->not->toContain('secret-token')
        ->and($payload['webhook_masked'])->toBe('https://services.leadconnectorhq.com/••••ab12')
        ->and(array_keys($payload))->toBe([
            'id',
            'enabled',
            'webhook_configured',
            'webhook_masked',
            'registration_webhook_configured',
            'registration_webhook_masked',
        ]);
});

test('a blank webhook url keeps the saved url and clear removes it', function () {
    $setting = saveGoHighLevel(true, GHL_WEBHOOK_URL);
    $webhook = app(GoHighLevelEnrollmentWebhook::class);

    $webhook->update($setting, false, '   ');
    $setting->refresh();

    expect($setting->fields['webhook_url'])->toBe(GHL_WEBHOOK_URL)
        ->and($setting->fields['enabled'])->toBeFalse();

    $webhook->clear($setting);
    $setting->refresh();

    expect($setting->fields['webhook_url'])->toBe('')
        ->and($webhook->adminPayload($setting)['webhook_configured'])->toBeFalse();
});

test('a connection error does not throw and the enrollment remains', function () {
    saveGoHighLevel(true, GHL_WEBHOOK_URL);
    $logged = collectGoHighLevelLogs();
    Http::fake(function () {
        throw new ConnectionException('Could not connect to '.GHL_WEBHOOK_URL);
    });

    $enrollment = enrollLearner('Jane Doe', 'jane@example.com', 'Alpha Takeoff');
    app()->terminate();

    expect(CourseEnrollment::query()->find($enrollment->id))->not->toBeNull();
    expectGoHighLevelFailureLog($logged, $enrollment->id, null);
});

test('a non-2xx response does not throw and the welcome email still runs', function () {
    saveGoHighLevel(true, GHL_WEBHOOK_URL);
    $logged = collectGoHighLevelLogs();
    Http::fake([
        'https://services.leadconnectorhq.com/*' => Http::response('no', 500),
    ]);

    $mail = Mockery::mock(CourseEnrollmentWelcomeMailService::class);
    $mail->shouldReceive('sendForEnrollment')->once()->andReturn(true);
    app()->instance(CourseEnrollmentWelcomeMailService::class, $mail);

    $enrollment = enrollLearner('Jane Doe', 'jane@example.com', 'Alpha Takeoff', sendWelcome: true);
    app()->terminate();

    expect(CourseEnrollment::query()->find($enrollment->id))->not->toBeNull();
    expectGoHighLevelFailureLog($logged, $enrollment->id, 500);
});

test('a webhook redirect is not followed', function () {
    saveGoHighLevel(true, GHL_WEBHOOK_URL);
    Http::fake([
        'https://services.leadconnectorhq.com/*' => Http::response('', 302, ['Location' => 'https://evil.example/collect']),
    ]);

    enrollLearner('Jane Doe', 'jane@example.com', 'Alpha Takeoff');
    app()->terminate();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->url() === GHL_WEBHOOK_URL);
});

test('saving the registration url does not change the payment url', function () {
    $setting = saveGoHighLevel(true, GHL_WEBHOOK_URL);
    $webhook = app(GoHighLevelEnrollmentWebhook::class);
    $registrationUrl = 'https://services.leadconnectorhq.com/hooks/register-token-cd34';

    $webhook->update($setting, true, '   ', $registrationUrl);
    $setting->refresh();

    expect($setting->fields['webhook_url'])->toBe(GHL_WEBHOOK_URL)
        ->and($setting->fields['registration_webhook_url'])->toBe($registrationUrl);

    $webhook->update($setting, true, 'https://services.msgsndr.com/hooks/new-pay-zz99', '   ');
    $setting->refresh();

    expect($setting->fields['webhook_url'])->toBe('https://services.msgsndr.com/hooks/new-pay-zz99')
        ->and($setting->fields['registration_webhook_url'])->toBe($registrationUrl);

    $webhook->clearRegistration($setting);
    $setting->refresh();

    expect($setting->fields['webhook_url'])->toBe('https://services.msgsndr.com/hooks/new-pay-zz99')
        ->and($setting->fields['registration_webhook_url'])->toBe('')
        ->and($webhook->adminPayload($setting)['registration_webhook_configured'])->toBeFalse()
        ->and($webhook->adminPayload($setting)['webhook_configured'])->toBeTrue();
});

test('a roofing account created from the roofing page includes that course', function () {
    Carbon\Carbon::setTestNow('2026-11-24 01:22:00');
    $registrationUrl = 'https://services.leadconnectorhq.com/hooks/register-token-cd34';
    saveGoHighLevel(true, GHL_WEBHOOK_URL, $registrationUrl);
    Http::fake([
        'https://services.leadconnectorhq.com/*' => Http::response(['ok' => true], 200),
    ]);

    $course = course('Roofing Estimating');
    $user = learner('Alexis Pacapac', 'alexis@example.com');

    app(GoHighLevelEnrollmentWebhook::class)->queueForRegistration($user, $course);
    app()->terminate();

    Http::assertSentCount(1);
    Http::assertSent(function ($request) use ($course, $user) {
        expect($request->url())->toBe('https://services.leadconnectorhq.com/hooks/register-token-cd34')
            ->and($request->data())->toBe([
                'first_name' => 'Alexis',
                'last_name' => 'Pacapac',
                'email' => 'alexis@example.com',
                'registered_at' => $user->fresh()->created_at->toIso8601String(),
                'source_course' => 'Roofing Estimating',
                'source_course_id' => (string) $course->id,
            ]);

        return true;
    });
});

test('a homepage signup leaves the course fields empty', function () {
    $registrationUrl = 'https://services.leadconnectorhq.com/hooks/register-token-cd34';
    saveGoHighLevel(true, GHL_WEBHOOK_URL, $registrationUrl);
    Http::fake([
        'https://services.leadconnectorhq.com/*' => Http::response(['ok' => true], 200),
    ]);

    $user = learner('Alexis Pacapac', 'home@example.com');
    app(GoHighLevelEnrollmentWebhook::class)->queueForRegistration($user, null);
    app()->terminate();

    Http::assertSent(function ($request) {
        expect($request->url())->toBe('https://services.leadconnectorhq.com/hooks/register-token-cd34')
            ->and($request->data()['source_course'])->toBe('')
            ->and($request->data()['source_course_id'])->toBe('');

        return true;
    });
    Http::assertNotSent(fn ($request) => $request->url() === GHL_WEBHOOK_URL);
});

test('a siding installment enrollment sends the plan coupon and amount to the payment url', function () {
    saveGoHighLevel(true, GHL_WEBHOOK_URL, 'https://services.leadconnectorhq.com/hooks/register-token-cd34');
    Http::fake([
        'https://services.leadconnectorhq.com/*' => Http::response(['ok' => true], 200),
    ]);

    $user = learner('Aireen Abasolo', 'aireen@example.com');
    $course = Course::query()->create([
        'title' => 'Siding Estimating',
        'slug' => 'siding-estimating',
        'status' => 'approved',
        'expiry_type' => 'lifetime',
        'billing_model' => 'one_time_or_installment',
        'installment_months' => 6,
    ]);
    PaymentHistory::query()->create([
        'user_id' => $user->id,
        'purchase_type' => Course::class,
        'purchase_id' => $course->id,
        'billing_type' => 'subscription',
        'coupon' => 'SIDING10',
        'amount' => 15,
        'transaction_id' => 'txn_siding',
    ]);
    PaymentHistory::query()->create([
        'user_id' => $user->id,
        'purchase_type' => Course::class,
        'purchase_id' => $course->id,
        'billing_type' => 'subscription_renewal',
        'coupon' => 'LATER',
        'amount' => 15,
        'transaction_id' => 'txn_siding_2',
    ]);

    app(CourseEnrollmentService::class)->createCourseEnroll([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'enrollment_type' => 'paid',
    ]);
    app()->terminate();

    Http::assertSentCount(1);
    Http::assertSent(function ($request) use ($course) {
        expect($request->url())->toBe(GHL_WEBHOOK_URL)
            ->and($request->data()['course'])->toBe('Siding Estimating')
            ->and($request->data()['course_id'])->toBe((string) $course->id)
            ->and($request->data()['payment_plan'])->toBe('installment_6')
            ->and($request->data()['coupon'])->toBe('SIDING10')
            ->and($request->data()['amount'])->toBe('15.00');

        return true;
    });
});

test('a course page redirect is kept on the sign up link', function () {
    $course = course('Roofing Estimating');
    $url = '/courses/details/'.$course->slug.'/'.$course->id;

    expect(\App\Support\RegistrationCourseSource::idFromUrl($url))->toBe($course->id)
        ->and(\App\Support\RegistrationCourseSource::idFromUrl('/register'))->toBeNull()
        ->and(\App\Support\RegistrationCourseSource::idFromUrl(route('home')))->toBeNull();

    $request = \Illuminate\Http\Request::create('/login', 'GET', ['redirect' => $url]);
    $request->setLaravelSession(app('session.store'));
    $response = app(\App\Http\Controllers\Auth\AuthenticatedSessionController::class)->create($request);
    $props = (new ReflectionClass($response))->getProperty('props')->getValue($response);

    expect($props['sourceCourseId'])->toBe($course->id);
});

test('the settings page names the roofing registration webhook apart from the payment webhook', function () {
    $page = file_get_contents(base_path('resources/js/pages/dashboard/settings/go-high-level.tsx'));

    expect($page)->toContain('Roofing registration webhook URL')
        ->and($page)->toContain('/register?source_course_id=7')
        ->and($page)->toContain('Payment webhook URL')
        ->and($page)->toContain('Remove payment URL')
        ->and($page)->toContain('Remove Roofing registration URL');
});

test('urls with credentials or the wrong scheme are rejected', function () {
    $webhook = app(GoHighLevelEnrollmentWebhook::class);

    expect($webhook->isAllowedWebhookUrl('http://services.leadconnectorhq.com/hooks/abc'))->toBeFalse()
        ->and($webhook->isAllowedWebhookUrl('https://user:pass@services.leadconnectorhq.com/hooks/abc'))->toBeFalse()
        ->and($webhook->isAllowedWebhookUrl('https://127.0.0.1/hooks/abc'))->toBeFalse()
        ->and($webhook->isAllowedWebhookUrl('https://services.msgsndr.com/hooks/abc'))->toBeTrue();
});

function collectGoHighLevelLogs(): \ArrayObject
{
    $logged = new \ArrayObject;

    Log::listen(function (MessageLogged $event) use ($logged) {
        if ($event->message === 'Go High Level enrollment webhook failed') {
            $logged->append($event);
        }
    });

    return $logged;
}

function expectGoHighLevelFailureLog(\ArrayObject $logged, int $enrollmentId, ?int $status): void
{
    expect($logged)->toHaveCount(1);

    $event = $logged[0];
    $encoded = json_encode($event->context);

    expect($event->level)->toBe('warning')
        ->and(array_keys($event->context))->toBe(['enrollment_id', 'http_status'])
        ->and($event->context['enrollment_id'])->toBe($enrollmentId)
        ->and($event->context['http_status'])->toBe($status)
        ->and(str_contains((string) $encoded, 'jane@example.com'))->toBeFalse()
        ->and(str_contains((string) $encoded, 'leadconnectorhq'))->toBeFalse();
}

function saveGoHighLevel(bool $enabled, string $url, string $registrationUrl = ''): Setting
{
    return Setting::query()->updateOrCreate(
        ['type' => GoHighLevelEnrollmentWebhook::SETTING_TYPE],
        [
            'title' => 'Go High Level',
            'fields' => [
                'enabled' => $enabled,
                'webhook_url' => $url,
                'registration_webhook_url' => $registrationUrl,
            ],
        ],
    );
}

function learner(string $name, string $email): User
{
    return User::query()->create([
        'name' => $name,
        'email' => $email,
        'password' => 'secret',
        'role' => 'student',
    ]);
}

function course(string $title): Course
{
    return Course::query()->create([
        'title' => $title,
        'slug' => str($title)->slug()->toString(),
        'status' => 'approved',
        'expiry_type' => 'lifetime',
    ]);
}

function enrollLearner(
    string $name,
    string $email,
    string $title,
    ?string $accessStatus = null,
    string $enrollmentType = 'free',
    bool $sendWelcome = false,
    ?User $user = null,
): CourseEnrollment {
    $user ??= User::query()->where('email', $email)->first() ?? learner($name, $email);
    $course = course($title);
    $payload = [
        'user_id' => $user->id,
        'course_id' => $course->id,
        'enrollment_type' => $enrollmentType,
    ];

    if ($accessStatus !== null) {
        $payload['access_status'] = $accessStatus;
    }

    return app(CourseEnrollmentService::class)->createCourseEnroll($payload, false, $sendWelcome);
}
