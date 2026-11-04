<?php

use App\Services\Payment\ActiveSubscriptionReport;
use App\Services\Payment\StripeActiveSubscriptionLister;
use App\Services\Payment\StripeCustomerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateways\Http\Controllers\Payment\ActiveSubscriptionReportController;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;
use Stripe\Stripe;

beforeEach(function () {
    config(['app.timezone' => 'UTC']);
    Schema::dropAllTables();

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->string('password')->nullable();
        $table->timestamps();
    });

    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('slug');
        $table->string('status')->default('approved');
        $table->timestamps();
    });

    Schema::create('subscriptions', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->string('stripe_subscription_id');
        $table->string('status')->default('active');
        $table->timestamps();
    });

    Schema::create('course_enrollments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->timestamps();
    });

    Schema::create('payment_histories', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->timestamps();
    });

    DB::table('users')->insert([
        ['id' => 5, 'name' => 'Ada Lovelace', 'email' => 'ada@academy.test', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 6, 'name' => 'Grace Hopper', 'email' => 'grace@academy.test', 'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::table('courses')->insert([
        ['id' => 9, 'title' => 'Drywall Estimating', 'slug' => 'drywall', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 10, 'title' => 'Roofing Estimating', 'slug' => 'roofing', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::table('subscriptions')->insert([
        'user_id' => 6,
        'course_id' => 10,
        'stripe_subscription_id' => 'sub_local',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

afterEach(function () {
    Stripe::setApiKey(null);
    ApiRequestor::setHttpClient(CurlClient::instance());
});

function activeSubscriptionRows(): array
{
    return [
        [
            'stripe_subscription_id' => 'sub_meta',
            'status' => 'active',
            'cancel_at_period_end' => false,
            'current_period_end' => 1800000000,
            'user_id' => 5,
            'course_id' => 9,
            'customer_email' => 'ignored@stripe.test',
            'customer_name' => 'Ignored',
        ],
        [
            'stripe_subscription_id' => 'sub_local',
            'status' => 'active',
            'cancel_at_period_end' => true,
            'current_period_end' => 1800000000,
            'user_id' => null,
            'course_id' => null,
            'customer_email' => 'other@stripe.test',
            'customer_name' => 'Other',
        ],
        [
            'stripe_subscription_id' => 'sub_unknown',
            'status' => 'active',
            'cancel_at_period_end' => false,
            'current_period_end' => null,
            'user_id' => null,
            'course_id' => null,
            'customer_email' => 'stripe-only@example.com',
            'customer_name' => 'Stripe Person',
        ],
        [
            'stripe_subscription_id' => 'sub_trial',
            'status' => 'trialing',
            'cancel_at_period_end' => false,
            'current_period_end' => 1800000000,
            'user_id' => 5,
            'course_id' => 9,
            'customer_email' => 'ada@academy.test',
            'customer_name' => 'Ada Lovelace',
        ],
    ];
}

function activeSubscriptionReport(): ActiveSubscriptionReport
{
    return new ActiveSubscriptionReport(new class(activeSubscriptionRows()) extends StripeActiveSubscriptionLister
    {
        public function __construct(private array $rows) {}

        public function list(): array
        {
            return $this->rows;
        }
    });
}

function tableCounts(): array
{
    return [
        'users' => DB::table('users')->count(),
        'courses' => DB::table('courses')->count(),
        'subscriptions' => DB::table('subscriptions')->count(),
        'enrollments' => DB::table('course_enrollments')->count(),
        'payments' => DB::table('payment_histories')->count(),
    ];
}

it('lists only active stripe subscriptions and matches academy students', function () {
    $rows = activeSubscriptionReport()->filtered([]);

    expect(collect($rows)->pluck('email')->all())->toBe([
        'ada@academy.test',
        'grace@academy.test',
        'stripe-only@example.com',
    ])
        ->and($rows[0]['student_name'])->toBe('Ada Lovelace')
        ->and($rows[0]['course'])->toBe('Drywall Estimating')
        ->and($rows[0]['stripe_status'])->toBe('active')
        ->and($rows[0]['period_end'])->toBe('January 15, 2027')
        ->and($rows[0]['cancel_label'])->toBe('No')
        ->and($rows[1]['course'])->toBe('Roofing Estimating')
        ->and($rows[1]['cancel_label'])->toBe('Yes')
        ->and($rows[2]['student_name'])->toBe('Stripe Person')
        ->and($rows[2]['course'])->toBe('');
});

it('limits rows with the name, course, and renewal filters', function () {
    $report = activeSubscriptionReport();

    expect(collect($report->filtered(['search' => 'ADA']))->pluck('email')->all())->toBe(['ada@academy.test'])
        ->and(collect($report->filtered(['course' => '10']))->pluck('email')->all())->toBe(['grace@academy.test'])
        ->and(collect($report->filtered(['renewal' => 'cancels']))->pluck('email')->all())->toBe(['grace@academy.test'])
        ->and(collect($report->filtered(['renewal' => 'renews']))->pluck('email')->all())->toBe([
            'ada@academy.test',
            'stripe-only@example.com',
        ]);
});

it('exports only the filtered rows and does not write academy records', function () {
    $before = tableCounts();
    $report = activeSubscriptionReport();
    $csv = $report->toCsv(['course' => '10', 'renewal' => 'cancels']);

    expect($csv)->toContain('Student name')
        ->and($csv)->toContain('grace@academy.test')
        ->and($csv)->toContain('Roofing Estimating')
        ->and($csv)->toContain('Yes')
        ->and($csv)->not->toContain('ada@academy.test')
        ->and($csv)->not->toContain('stripe-only@example.com')
        ->and(tableCounts())->toBe($before);

    $controller = new ActiveSubscriptionReportController($report);
    $response = $controller->export(Request::create('/dashboard/payment-reports/active-subscriptions/export', 'GET', [
        'search' => 'ada',
    ]));

    expect($response->headers->get('content-disposition'))->toContain('active-subscriptions-'.now()->format('Y-m-d').'.csv')
        ->and($response->getContent())->toContain('ada@academy.test')
        ->and($response->getContent())->not->toContain('grace@academy.test')
        ->and(tableCounts())->toBe($before);

    $controller->index(Request::create('/dashboard/payment-reports/active-subscriptions', 'GET'));

    expect(tableCounts())->toBe($before);
});

it('asks stripe only for active subscriptions and does not send an update', function () {
    $calls = [];
    $client = new class($calls) implements ClientInterface
    {
        public function __construct(private array &$calls) {}

        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1')
        {
            $this->calls[] = compact('method', 'absUrl', 'params');

            $body = [
                'object' => 'list',
                'url' => '/v1/subscriptions',
                'has_more' => false,
                'data' => [
                    [
                        'id' => 'sub_active',
                        'object' => 'subscription',
                        'status' => 'active',
                        'cancel_at_period_end' => false,
                        'current_period_end' => 1800000000,
                        'customer' => [
                            'id' => 'cus_1',
                            'object' => 'customer',
                            'email' => 'ada@academy.test',
                            'name' => 'Ada',
                        ],
                        'metadata' => ['user_id' => '5', 'course_id' => '9'],
                        'items' => ['object' => 'list', 'data' => [], 'has_more' => false, 'url' => '/v1/subscription_items'],
                    ],
                    [
                        'id' => 'sub_trial',
                        'object' => 'subscription',
                        'status' => 'trialing',
                        'cancel_at_period_end' => false,
                        'customer' => 'cus_2',
                        'metadata' => [],
                        'items' => ['object' => 'list', 'data' => [], 'has_more' => false, 'url' => '/v1/subscription_items'],
                    ],
                ],
            ];

            return [json_encode($body), 200, []];
        }
    };

    ApiRequestor::setHttpClient($client);

    $stripe = Mockery::mock(StripeCustomerService::class);
    $stripe->shouldReceive('configureStripe')->once()->andReturnUsing(function () {
        Stripe::setApiKey('sk_test_active_list');
    });

    $rows = (new StripeActiveSubscriptionLister($stripe))->list();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['stripe_subscription_id'])->toBe('sub_active')
        ->and($rows[0]['status'])->toBe('active')
        ->and($calls)->toHaveCount(1)
        ->and($calls[0]['method'])->toBe('get')
        ->and($calls[0]['params']['status'])->toBe('active')
        ->and(collect($calls)->pluck('method')->unique()->all())->toBe(['get']);
});
