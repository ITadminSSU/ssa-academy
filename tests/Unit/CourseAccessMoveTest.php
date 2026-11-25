<?php

use App\Enums\EnrollmentAccessStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Course\CourseEnrollmentService;
use App\Services\Payment\CourseAccessMoveService;
use App\Services\Payment\StripeCustomerService;
use App\Services\Payment\SubscriptionAccessService;
use App\Services\Payment\SubscriptionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateways\Services\PaymentService;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;

beforeEach(function () {
    Schema::dropIfExists('watch_histories');
    Schema::dropIfExists('course_enrollments');
    Schema::dropIfExists('subscriptions');
    Schema::dropIfExists('courses');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('role')->nullable();
        $table->string('user_type')->nullable();
        $table->string('candidate_status')->nullable();
        $table->timestamp('candidate_status_updated_at')->nullable();
        $table->timestamps();
    });

    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('status')->nullable();
        $table->string('billing_model')->nullable();
        $table->unsignedBigInteger('instructor_id')->nullable();
        $table->timestamp('launch_at')->nullable();
        $table->timestamps();
    });

    Schema::create('subscriptions', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->string('stripe_customer_id')->nullable();
        $table->string('stripe_subscription_id')->nullable();
        $table->string('stripe_price_id')->nullable();
        $table->string('status');
        $table->timestamp('current_period_start')->nullable();
        $table->timestamp('current_period_end')->nullable();
        $table->timestamp('access_retained_at')->nullable();
        $table->boolean('cancel_at_period_end')->default(false);
        $table->timestamp('canceled_at')->nullable();
        $table->timestamp('grace_ends_at')->nullable();
        $table->timestamps();
    });

    Schema::create('course_enrollments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->string('enrollment_type');
        $table->string('access_status');
        $table->boolean('pef_granted')->default(false);
        $table->timestamp('entry_date')->nullable();
        $table->timestamp('expiry_date')->nullable();
        $table->timestamp('suspended_at')->nullable();
        $table->unsignedBigInteger('subscription_id')->nullable();
        $table->decimal('deposit_amount', 10, 2)->nullable();
        $table->timestamp('deposit_paid_at')->nullable();
        $table->unsignedBigInteger('deposit_payment_history_id')->nullable();
        $table->decimal('balance_amount', 10, 2)->nullable();
        $table->timestamp('balance_due_at')->nullable();
        $table->timestamp('balance_deadline_at')->nullable();
        $table->timestamp('balance_paid_at')->nullable();
        $table->unsignedBigInteger('balance_payment_history_id')->nullable();
        $table->timestamp('forfeited_at')->nullable();
        $table->string('launch_offer_cohort')->nullable();
        $table->timestamp('welcome_email_sent_at')->nullable();
        $table->timestamps();
    });

    Schema::create('watch_histories', function (Blueprint $table) {
        $table->id();
        $table->string('current_section_id');
        $table->string('current_watching_id');
        $table->string('current_watching_type');
        $table->json('completed_watching')->nullable();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->timestamps();
    });
});

afterEach(function () {
    ApiRequestor::setHttpClient(new CurlClient);
    Mockery::close();
});

test('a subscription move updates stripe metadata and keeps the old progress locked', function () {
    $world = moveWorld();
    $log = bindMoveStripe();

    $opened = app(CourseAccessMoveService::class)->move($world['enrollment'], $world['drywall']->id);

    $lumber = $world['enrollment']->fresh();
    $subscription = $world['subscription']->fresh();
    $call = $log->calls[0];

    expect($call['method'])->toBe('post')
        ->and($call['absUrl'])->toContain('sub_move')
        ->and($call['params']['metadata']['course_id'])->toBe((string) $world['drywall']->id)
        ->and($call['params'])->not->toHaveKey('items')
        ->and($lumber->access_status)->toBe(EnrollmentAccessStatus::TRANSFERRED)
        ->and($lumber->subscription_id)->toBeNull()
        ->and($opened->course_id)->toBe($world['drywall']->id)
        ->and($opened->access_status)->toBe(EnrollmentAccessStatus::ACTIVE)
        ->and($opened->subscription_id)->toBe($subscription->id)
        ->and($subscription->course_id)->toBe($world['drywall']->id)
        ->and($subscription->stripe_subscription_id)->toBe('sub_move')
        ->and($subscription->stripe_price_id)->toBe('price_lumber')
        ->and(DB::table('watch_histories')->where('course_id', $world['lumber']->id)->count())->toBe(1)
        ->and(app(SubscriptionAccessService::class)->getAccessMode($world['user'], $world['lumber'], $lumber))->toBe('none');
});

test('a webhook for the new course does not unlock the course that was left', function () {
    $world = moveWorld();
    bindMoveStripe();
    app(CourseAccessMoveService::class)->move($world['enrollment'], $world['drywall']->id);

    moveSubscriptionService()->syncFromStripeSubscription(stripeSubscriptionPayload($world['user']->id, $world['drywall']->id));

    $lumber = $world['enrollment']->fresh();
    $drywall = CourseEnrollment::query()->where('course_id', $world['drywall']->id)->first();

    expect($lumber->access_status)->toBe(EnrollmentAccessStatus::TRANSFERRED)
        ->and($lumber->subscription_id)->toBeNull()
        ->and($drywall->access_status)->toBe(EnrollmentAccessStatus::ACTIVE)
        ->and($drywall->subscription_id)->toBe($world['subscription']->id);
});

test('a webhook that still names the old course cannot leave both courses open', function () {
    $world = moveWorld();
    bindMoveStripe();
    app(CourseAccessMoveService::class)->move($world['enrollment'], $world['drywall']->id);

    moveSubscriptionService()->syncFromStripeSubscription(stripeSubscriptionPayload($world['user']->id, $world['lumber']->id));

    $open = CourseEnrollment::query()
        ->where('user_id', $world['user']->id)
        ->where('access_status', EnrollmentAccessStatus::ACTIVE->value)
        ->whereNotNull('subscription_id')
        ->get();

    expect($open)->toHaveCount(1)
        ->and($open->first()->course_id)->toBe($world['lumber']->id)
        ->and(CourseEnrollment::query()->where('course_id', $world['drywall']->id)->first()->access_status)
        ->toBe(EnrollmentAccessStatus::TRANSFERRED);
});

test('moving back reopens the previous course and its saved progress', function () {
    $world = moveWorld();
    bindMoveStripe();
    $opened = app(CourseAccessMoveService::class)->move($world['enrollment'], $world['drywall']->id);

    $returned = app(CourseAccessMoveService::class)->move($opened->fresh(), $world['lumber']->id);

    expect($returned->id)->toBe($world['enrollment']->id)
        ->and($returned->access_status)->toBe(EnrollmentAccessStatus::ACTIVE)
        ->and($returned->subscription_id)->toBe($world['subscription']->id)
        ->and($opened->fresh()->access_status)->toBe(EnrollmentAccessStatus::TRANSFERRED)
        ->and($opened->fresh()->subscription_id)->toBeNull()
        ->and($world['subscription']->fresh()->stripe_subscription_id)->toBe('sub_move')
        ->and($world['subscription']->fresh()->course_id)->toBe($world['lumber']->id)
        ->and(DB::table('watch_histories')->where('course_id', $world['lumber']->id)->value('completed_watching'))->not->toBeNull()
        ->and(collect(app(CourseAccessMoveService::class)->destinationOptions($returned->fresh()))->firstWhere('id', $world['drywall']->id)['previous'])->toBeTrue();
});

test('a one-time move does not call stripe', function () {
    $world = moveWorld(stripeSubscriptionId: null);
    $log = bindMoveStripe(expectCall: false);

    $opened = app(CourseAccessMoveService::class)->move($world['enrollment'], $world['drywall']->id);

    expect($log->calls)->toBe([])
        ->and($opened->course_id)->toBe($world['drywall']->id)
        ->and($opened->subscription_id)->toBeNull()
        ->and($world['enrollment']->fresh()->access_status)->toBe(EnrollmentAccessStatus::TRANSFERRED)
        ->and(Subscription::query()->count())->toBe(0);
});

test('stripe rejection leaves the original course open', function () {
    $world = moveWorld();
    bindMoveStripe(status: 404);

    expect(fn () => app(CourseAccessMoveService::class)->move($world['enrollment'], $world['drywall']->id))
        ->toThrow(RuntimeException::class);

    expect($world['enrollment']->fresh()->access_status)->toBe(EnrollmentAccessStatus::ACTIVE)
        ->and($world['enrollment']->fresh()->subscription_id)->toBe($world['subscription']->id)
        ->and($world['subscription']->fresh()->course_id)->toBe($world['lumber']->id)
        ->and(CourseEnrollment::query()->where('course_id', $world['drywall']->id)->exists())->toBeFalse();
});

test('a move refuses the same course and a course the student already has', function () {
    $world = moveWorld();
    bindMoveStripe();
    CourseEnrollment::query()->create([
        'user_id' => $world['user']->id,
        'course_id' => $world['drywall']->id,
        'enrollment_type' => 'paid',
        'access_status' => EnrollmentAccessStatus::ACTIVE,
        'entry_date' => now(),
    ]);

    expect(fn () => app(CourseAccessMoveService::class)->move($world['enrollment'], $world['lumber']->id))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => app(CourseAccessMoveService::class)->move($world['enrollment']->fresh(), $world['drywall']->id))
        ->toThrow(InvalidArgumentException::class);

    expect($world['subscription']->fresh()->course_id)->toBe($world['lumber']->id)
        ->and($world['enrollment']->fresh()->access_status)->toBe(EnrollmentAccessStatus::ACTIVE);
});

test('a reserved seat moves as a reservation and a companion enrollment stays', function () {
    $world = moveWorld(stripeSubscriptionId: null, reserved: true);
    $companionCourse = Course::query()->create(['title' => 'Companion', 'status' => 'approved']);
    $companion = CourseEnrollment::query()->create([
        'user_id' => $world['user']->id,
        'course_id' => $companionCourse->id,
        'enrollment_type' => 'free',
        'access_status' => EnrollmentAccessStatus::ACTIVE,
        'entry_date' => now(),
    ]);
    bindMoveStripe(expectCall: false);

    $opened = app(CourseAccessMoveService::class)->move($world['enrollment'], $world['drywall']->id);

    expect($opened->access_status)->toBe(EnrollmentAccessStatus::RESERVED)
        ->and($opened->deposit_payment_history_id)->toBe(44)
        ->and($world['enrollment']->fresh()->access_status)->toBe(EnrollmentAccessStatus::TRANSFERRED)
        ->and($world['enrollment']->fresh()->deposit_payment_history_id)->toBeNull()
        ->and($companion->fresh()->access_status)->toBe(EnrollmentAccessStatus::ACTIVE)
        ->and(CourseEnrollment::query()->where('course_id', $companionCourse->id)->count())->toBe(1);
});

test('a retained subscription moves without a new stripe subscription', function () {
    $world = moveWorld(retained: true);
    bindMoveStripe();

    app(CourseAccessMoveService::class)->move($world['enrollment'], $world['drywall']->id);

    $subscription = $world['subscription']->fresh();

    expect(Subscription::query()->count())->toBe(1)
        ->and($subscription->stripe_subscription_id)->toBe('sub_move')
        ->and($subscription->access_retained_at)->not->toBeNull()
        ->and($subscription->course_id)->toBe($world['drywall']->id)
        ->and(file_get_contents(base_path('resources/js/pages/dashboard/enrollments/partials/admin-table-columns.tsx')))->toContain('Keep course')
        ->and(file_get_contents(base_path('resources/js/pages/dashboard/enrollments/exams.tsx')))->not->toContain('Move course');
});

function moveWorld(?string $stripeSubscriptionId = 'sub_move', bool $reserved = false, bool $retained = false): array
{
    $user = User::query()->create([
        'name' => 'A Student',
        'email' => 'student@example.com',
        'role' => 'student',
        'user_type' => 'external',
    ]);
    $lumber = Course::query()->create(['title' => 'Lumber', 'status' => 'approved', 'billing_model' => 'subscription']);
    $drywall = Course::query()->create(['title' => 'Drywall', 'status' => 'approved', 'billing_model' => 'subscription']);
    $subscription = null;
    $subscriptionId = null;

    if ($stripeSubscriptionId !== null || $retained) {
        $subscription = Subscription::query()->create([
            'user_id' => $user->id,
            'course_id' => $lumber->id,
            'stripe_customer_id' => 'cus_1',
            'stripe_subscription_id' => $stripeSubscriptionId,
            'stripe_price_id' => 'price_lumber',
            'status' => $retained ? SubscriptionStatus::CANCELED : SubscriptionStatus::ACTIVE,
            'access_retained_at' => $retained ? now() : null,
        ]);
        $subscriptionId = $subscription->id;
    }

    $enrollment = CourseEnrollment::query()->create([
        'user_id' => $user->id,
        'course_id' => $lumber->id,
        'enrollment_type' => $reserved ? 'deposit' : 'subscription',
        'access_status' => $reserved ? EnrollmentAccessStatus::RESERVED : EnrollmentAccessStatus::ACTIVE,
        'subscription_id' => $subscriptionId,
        'entry_date' => now(),
        'deposit_amount' => $reserved ? 100 : null,
        'deposit_paid_at' => $reserved ? now() : null,
        'deposit_payment_history_id' => $reserved ? 44 : null,
        'welcome_email_sent_at' => now(),
    ]);

    DB::table('watch_histories')->insert([
        'current_section_id' => '1',
        'current_watching_id' => '9',
        'current_watching_type' => 'lesson',
        'completed_watching' => json_encode([['id' => 9, 'type' => 'lesson']]),
        'user_id' => $user->id,
        'course_id' => $lumber->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return compact('user', 'lumber', 'drywall', 'subscription', 'enrollment');
}

function bindMoveStripe(bool $expectCall = true, int $status = 200): object
{
    $log = new stdClass;
    $log->calls = [];

    $client = new class($log, $status) implements ClientInterface
    {
        public function __construct(private object $log, private int $status) {}

        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1')
        {
            $this->log->calls[] = compact('method', 'absUrl', 'params');

            if ($this->status >= 400) {
                return [json_encode([
                    'error' => [
                        'message' => 'No such subscription',
                        'type' => 'invalid_request_error',
                    ],
                ]), $this->status, []];
            }

            return [json_encode([
                'id' => 'sub_move',
                'object' => 'subscription',
                'status' => 'active',
                'customer' => 'cus_1',
                'metadata' => ['course_id' => (string) ($params['metadata']['course_id'] ?? '')],
                'items' => ['object' => 'list', 'data' => []],
            ]), 200, []];
        }
    };

    ApiRequestor::setHttpClient($client);

    $stripe = Mockery::mock(StripeCustomerService::class);
    $expectation = $stripe->shouldReceive('configureStripe');
    if ($expectCall) {
        $expectation->andReturnUsing(fn () => Stripe::setApiKey('sk_test_move'));
    } else {
        $expectation->never();
    }
    app()->instance(StripeCustomerService::class, $stripe);

    return $log;
}

function moveSubscriptionService(): SubscriptionService
{
    return new SubscriptionService(
        Mockery::mock(CourseEnrollmentService::class),
        Mockery::mock(PaymentService::class),
        Mockery::mock(StripeCustomerService::class),
    );
}

function stripeSubscriptionPayload(int $userId, int $courseId): StripeSubscription
{
    return StripeSubscription::constructFrom([
        'id' => 'sub_move',
        'object' => 'subscription',
        'customer' => 'cus_1',
        'status' => 'active',
        'metadata' => [
            'user_id' => (string) $userId,
            'course_id' => (string) $courseId,
        ],
        'items' => [
            'object' => 'list',
            'data' => [
                ['id' => 'si_1', 'price' => ['id' => 'price_lumber']],
            ],
        ],
        'cancel_at_period_end' => false,
    ]);
}
