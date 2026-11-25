<?php

use App\Enums\CourseBillingModel;
use App\Enums\EnrollmentAccessStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\InstallmentBillingService;
use App\Services\Payment\LaunchOfferEnrollmentService;
use App\Services\Payment\RetainedAccessService;
use App\Services\Payment\StripeCustomerService;
use App\Services\Payment\StripeWebhookService;
use App\Services\Payment\SubscriptionAccessService;
use App\Services\Payment\SubscriptionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateways\Services\PaymentService;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;
use Stripe\Stripe;

beforeEach(function () {
    Schema::dropIfExists('course_enrollments');
    Schema::dropIfExists('subscriptions');
    Schema::dropIfExists('courses');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('role')->nullable();
        $table->timestamps();
    });

    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('slug')->nullable();
        $table->string('pricing_type')->nullable();
        $table->string('billing_model')->nullable();
        $table->string('thumbnail')->nullable();
        $table->decimal('subscription_price', 10, 2)->nullable();
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
        $table->timestamp('access_retained_at')->nullable();
        $table->string('open_invoice_url')->nullable();
        $table->timestamp('choice_installment_completed_at')->nullable();
        $table->timestamp('current_period_start')->nullable();
        $table->timestamp('current_period_end')->nullable();
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
        $table->timestamp('suspended_at')->nullable();
        $table->unsignedBigInteger('subscription_id')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    ApiRequestor::setHttpClient(new CurlClient);
    Mockery::close();
});

it('keeps full course access after a canceled stripe subscription', function () {
    $user = User::query()->create(['name' => 'A Student', 'email' => 'student@example.com', 'role' => 'student']);
    $course = Course::query()->create([
        'title' => 'Estimating',
        'slug' => 'estimating',
        'pricing_type' => 'paid',
        'billing_model' => CourseBillingModel::ONE_TIME_OR_MONTHLY->value,
    ]);
    $subscription = Subscription::query()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'stripe_customer_id' => 'cus_old',
        'stripe_subscription_id' => 'sub_old',
        'stripe_price_id' => 'price_old',
        'status' => SubscriptionStatus::CANCELED,
        'canceled_at' => now(),
    ]);
    $enrollment = CourseEnrollment::query()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'enrollment_type' => 'subscription',
        'access_status' => EnrollmentAccessStatus::SUSPENDED,
        'suspended_at' => now(),
        'subscription_id' => $subscription->id,
    ]);

    $kept = app(RetainedAccessService::class)->keepCourse($enrollment);

    expect($kept->access_status)->toBe(EnrollmentAccessStatus::ACTIVE)
        ->and($kept->enrollment_type)->toBe('paid')
        ->and($kept->suspended_at)->toBeNull()
        ->and($kept->subscription->accessRetained())->toBeTrue()
        ->and($kept->subscription->status)->toBe(SubscriptionStatus::CANCELED);

    app(SubscriptionService::class)->suspend($kept->subscription->fresh());

    $afterWebhook = $enrollment->fresh();

    expect($afterWebhook->access_status)->toBe(EnrollmentAccessStatus::ACTIVE)
        ->and($afterWebhook->enrollment_type)->toBe('paid');

    $mode = app(SubscriptionAccessService::class)->getAccessMode($user, $course->fresh(), $afterWebhook->fresh('subscription'));

    expect($mode)->toBe('full');
});

it('keeps a payment-plan course open when billing was stopped on purpose', function () {
    $user = new User(['id' => 4, 'role' => 'student']);
    $course = new Course([
        'billing_model' => CourseBillingModel::ONE_TIME_OR_INSTALLMENT,
        'pricing_type' => 'paid',
    ]);
    $subscription = new Subscription([
        'status' => SubscriptionStatus::CANCELED,
        'access_retained_at' => now(),
    ]);
    $enrollment = new CourseEnrollment([
        'access_status' => EnrollmentAccessStatus::ACTIVE,
        'enrollment_type' => 'paid',
    ]);
    $enrollment->setRelation('subscription', $subscription);

    expect(app(SubscriptionAccessService::class)->getAccessMode($user, $course, $enrollment))->toBe('full');
});

it('offers the whole course to a kept student on a canceled old plan', function () {
    $user = User::query()->create(['name' => 'Aireen', 'email' => 'aireen@example.com', 'role' => 'student']);
    $course = Course::query()->create([
        'title' => 'Siding Estimating',
        'slug' => 'siding-estimating',
        'pricing_type' => 'paid',
        'billing_model' => CourseBillingModel::ONE_TIME_OR_INSTALLMENT->value,
    ]);
    $subscription = Subscription::query()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'stripe_customer_id' => 'cus_kept',
        'stripe_subscription_id' => 'sub_kept',
        'stripe_price_id' => 'price_old_monthly',
        'status' => SubscriptionStatus::CANCELED,
        'access_retained_at' => now(),
        'open_invoice_url' => 'https://pay.stripe.com/invoice/old',
        'current_period_end' => now()->addMonth(),
        'canceled_at' => now(),
    ]);
    $enrollment = CourseEnrollment::query()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'enrollment_type' => 'paid',
        'access_status' => EnrollmentAccessStatus::SUSPENDED,
        'suspended_at' => now(),
        'subscription_id' => $subscription->id,
    ]);

    $card = collect(app(SubscriptionService::class)->listForUser($user))->first();

    expect($card['grants_full_access'])->toBeTrue()
        ->and($card['access_retained'])->toBeTrue()
        ->and($card['status'])->toBe('canceled')
        ->and($card['course']->id)->toBe($course->id);

    expect(app(LaunchOfferEnrollmentService::class)->resolveCheckoutMode($user, $course->fresh()))->toBeNull();

    $enrollment->subscription->open_invoice_url = 'https://pay.stripe.com/invoice/old';
    $access = app(SubscriptionAccessService::class)->toFrontendPayload($user, $course->fresh(), $enrollment->fresh('subscription'));

    expect($access['mode'])->toBe('full')
        ->and($access['can_mark_progress'])->toBeTrue()
        ->and($access['can_resubscribe'])->toBeFalse()
        ->and($access['can_pay_invoice'])->toBeFalse()
        ->and($access['invoice_url'])->toBeNull();

    $enrollment->update([
        'access_status' => EnrollmentAccessStatus::SUSPENDED,
        'suspended_at' => now(),
    ]);

    retainedSubscriptionUpdated($user, $course, 'past_due');

    expect($enrollment->fresh()->access_status)->toBe(EnrollmentAccessStatus::ACTIVE)
        ->and($enrollment->fresh()->suspended_at)->toBeNull()
        ->and($enrollment->fresh()->enrollment_type)->toBe('paid');

    retainedSubscriptionUpdated($user, $course, 'canceled');

    expect($enrollment->fresh()->access_status)->toBe(EnrollmentAccessStatus::ACTIVE)
        ->and($subscription->fresh()->accessRetained())->toBeTrue();
});

it('still sends a canceled student who was not kept to the open installment', function () {
    $user = User::query()->create(['name' => 'Late Payer', 'email' => 'late@example.com', 'role' => 'student']);
    $course = Course::query()->create([
        'title' => 'Siding Estimating',
        'slug' => 'siding-late',
        'pricing_type' => 'paid',
        'billing_model' => CourseBillingModel::ONE_TIME_OR_INSTALLMENT->value,
    ]);
    $subscription = Subscription::query()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'stripe_customer_id' => 'cus_late',
        'stripe_subscription_id' => 'sub_late',
        'stripe_price_id' => 'price_installment',
        'status' => SubscriptionStatus::CANCELED,
        'open_invoice_url' => 'https://pay.stripe.com/invoice/due',
        'canceled_at' => now(),
    ]);
    CourseEnrollment::query()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'enrollment_type' => 'subscription',
        'access_status' => EnrollmentAccessStatus::SUSPENDED,
        'suspended_at' => now(),
        'subscription_id' => $subscription->id,
    ]);

    expect($subscription->fresh()->grantsFullAccess())->toBeFalse()
        ->and(app(LaunchOfferEnrollmentService::class)->resolveCheckoutMode($user, $course->fresh()))->toBe('choice_installment_past_due');
});

it('keeps course access when stripe no longer has the subscription', function () {
    [$user, $course, $subscription, $enrollment] = retainedEnrollment(SubscriptionStatus::ACTIVE, 'sub_missing');
    $log = bindKeepStripe(status: 404);

    $kept = app(RetainedAccessService::class)->keepCourse($enrollment);

    expect($log->calls)->toHaveCount(1)
        ->and($log->calls[0]['method'])->toBe('get')
        ->and($kept->access_status)->toBe(EnrollmentAccessStatus::ACTIVE)
        ->and($kept->subscription->accessRetained())->toBeTrue()
        ->and($kept->subscription->status)->toBe(SubscriptionStatus::CANCELED)
        ->and($kept->subscription->stripe_subscription_id)->toBe('sub_missing')
        ->and(app(SubscriptionAccessService::class)->getAccessMode($user, $course->fresh(), $kept->fresh('subscription')))->toBe('full');
});

it('keeps a student whose subscription was already cancelled in stripe', function () {
    [$user, $course, $subscription, $enrollment] = retainedEnrollment(SubscriptionStatus::ACTIVE, 'sub_already');
    $log = bindKeepStripe(stripeStatus: 'canceled');

    $kept = app(RetainedAccessService::class)->keepCourse($enrollment);

    expect(collect($log->calls)->pluck('method')->all())->toBe(['get'])
        ->and($kept->access_status)->toBe(EnrollmentAccessStatus::ACTIVE)
        ->and($kept->subscription->accessRetained())->toBeTrue()
        ->and($kept->subscription->status)->toBe(SubscriptionStatus::CANCELED)
        ->and(app(SubscriptionAccessService::class)->getAccessMode($user, $course->fresh(), $kept))->toBe('full');
});

it('does not mark a course kept when stripe fails for another reason', function () {
    [, , $subscription, $enrollment] = retainedEnrollment(SubscriptionStatus::ACTIVE, 'sub_down');
    bindKeepStripe(status: 401);

    expect(fn () => app(RetainedAccessService::class)->keepCourse($enrollment))->toThrow(Exception::class);

    expect($subscription->fresh()->access_retained_at)->toBeNull()
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::ACTIVE)
        ->and($enrollment->fresh()->access_status)->toBe(EnrollmentAccessStatus::SUSPENDED);
});

function retainedEnrollment(SubscriptionStatus $status, string $stripeId): array
{
    $user = User::query()->create([
        'name' => 'Kept Student',
        'email' => $stripeId.'@example.com',
        'role' => 'student',
    ]);
    $course = Course::query()->create([
        'title' => 'Lumber',
        'slug' => $stripeId,
        'pricing_type' => 'paid',
        'billing_model' => CourseBillingModel::SUBSCRIPTION->value,
    ]);
    $subscription = Subscription::query()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'stripe_customer_id' => 'cus_keep',
        'stripe_subscription_id' => $stripeId,
        'stripe_price_id' => 'price_keep',
        'status' => $status,
    ]);
    $enrollment = CourseEnrollment::query()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'enrollment_type' => 'subscription',
        'access_status' => EnrollmentAccessStatus::SUSPENDED,
        'suspended_at' => now(),
        'subscription_id' => $subscription->id,
    ]);

    return [$user, $course, $subscription, $enrollment];
}

function bindKeepStripe(int $status = 200, string $stripeStatus = 'active'): object
{
    $log = new stdClass;
    $log->calls = [];

    $client = new class($log, $status, $stripeStatus) implements ClientInterface
    {
        public function __construct(private object $log, private int $status, private string $stripeStatus) {}

        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1')
        {
            $this->log->calls[] = compact('method', 'absUrl', 'params');

            if ($this->status >= 400) {
                return [json_encode([
                    'error' => [
                        'code' => 'resource_missing',
                        'message' => 'No such subscription',
                        'type' => 'invalid_request_error',
                    ],
                ]), $this->status, []];
            }

            return [json_encode([
                'id' => 'sub_keep',
                'object' => 'subscription',
                'status' => $method === 'delete' ? 'canceled' : $this->stripeStatus,
                'customer' => 'cus_keep',
                'items' => ['object' => 'list', 'data' => []],
            ]), 200, []];
        }
    };

    ApiRequestor::setHttpClient($client);

    $stripe = Mockery::mock(StripeCustomerService::class);
    $stripe->shouldReceive('configureStripe')->andReturnUsing(fn () => Stripe::setApiKey('sk_test_keep'));
    app()->instance(StripeCustomerService::class, $stripe);

    return $log;
}

function retainedSubscriptionUpdated(User $user, Course $course, string $status): void
{
    $stripeCustomer = Mockery::mock(StripeCustomerService::class);
    $stripeCustomer->shouldReceive('configureStripe');

    $webhook = new StripeWebhookService(
        $stripeCustomer,
        app(SubscriptionService::class),
        app(LaunchOfferEnrollmentService::class),
        Mockery::mock(PaymentService::class),
        app(InstallmentBillingService::class),
    );

    $method = new ReflectionMethod(StripeWebhookService::class, 'handleSubscriptionUpdated');
    $method->invoke($webhook, (object) [
        'id' => 'sub_kept',
        'customer' => 'cus_kept',
        'status' => $status,
        'metadata' => [
            'user_id' => (string) $user->id,
            'course_id' => (string) $course->id,
        ],
        'items' => (object) [
            'data' => [
                (object) ['price' => (object) ['id' => 'price_old_monthly']],
            ],
        ],
        'current_period_start' => now()->subMonth()->timestamp,
        'current_period_end' => now()->addMonth()->timestamp,
        'cancel_at_period_end' => false,
        'canceled_at' => $status === 'canceled' ? now()->timestamp : null,
    ]);
}
