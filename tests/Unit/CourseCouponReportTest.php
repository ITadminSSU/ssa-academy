<?php

use App\Models\Course\CourseCoupon;
use App\Models\Course\CourseCouponLabel;
use App\Models\User;
use App\Services\Course\CourseCouponService;
use App\Support\CourseCouponStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('payment_histories');
    Schema::dropIfExists('course_coupons');
    Schema::dropIfExists('course_coupon_labels');
    Schema::dropIfExists('courses');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->string('role')->nullable();
        $table->unsignedBigInteger('instructor_id')->nullable();
        $table->timestamps();
    });

    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->unsignedBigInteger('instructor_id')->nullable();
        $table->timestamps();
    });

    Schema::create('course_coupon_labels', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('color');
        $table->timestamps();
    });

    Schema::create('course_coupons', function (Blueprint $table) {
        $table->id();
        $table->string('code');
        $table->string('discount_type')->nullable();
        $table->decimal('discount', 8, 2)->nullable();
        $table->unsignedBigInteger('course_id')->nullable();
        $table->unsignedBigInteger('course_coupon_label_id')->nullable();
        $table->unsignedBigInteger('created_by')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamp('valid_from')->nullable();
        $table->timestamp('valid_to')->nullable();
        $table->unsignedInteger('usage_limit')->nullable();
        $table->unsignedInteger('used_count')->default(0);
        $table->timestamps();
    });

    Schema::create('payment_histories', function (Blueprint $table) {
        $table->id();
        $table->string('coupon')->nullable();
        $table->json('meta')->nullable();
        $table->timestamps();
    });
});

function couponReportModel(array $attributes): CourseCoupon
{
    $coupon = new CourseCoupon();
    $coupon->forceFill($attributes);

    return $coupon;
}

test('coupon status follows the list order', function () {
    $now = now();

    expect(CourseCouponStatus::key(false, null, $now->copy()->subDay(), null, 0, $now))->toBe('inactive')
        ->and(CourseCouponStatus::key(true, $now->copy()->addDay(), $now->copy()->subDay(), null, 0, $now))->toBe('expired')
        ->and(CourseCouponStatus::key(true, $now->copy()->addDay(), $now->copy()->addDays(2), 1, 1, $now))->toBe('scheduled')
        ->and(CourseCouponStatus::key(true, null, null, 1, 1, $now))->toBe('used_up')
        ->and(CourseCouponStatus::key(true, null, null, null, 3, $now))->toBe('active');
});

test('the report sums redemptions and matches any selected chip', function () {
    $holiday = couponReportModel([
        'is_active' => true,
        'course_coupon_label_id' => 5,
        'usage_limit' => null,
        'used_count' => 99,
    ]);
    $holiday->setAttribute('usages_count', 2);

    $plain = couponReportModel([
        'is_active' => false,
        'course_coupon_label_id' => null,
        'used_count' => 4,
    ]);
    $plain->setAttribute('usages_count', 1);

    $partner = couponReportModel([
        'is_active' => true,
        'course_coupon_label_id' => 8,
        'usage_limit' => 1,
        'used_count' => 0,
    ]);
    $partner->setAttribute('usages_count', 1);

    $service = new CourseCouponService();
    $all = $service->summarizeCouponReport([$holiday, $plain, $partner], []);

    expect($all['filtered'])->toBeFalse()
        ->and($all['total_coupons'])->toBe(3)
        ->and($all['successful_redemptions'])->toBe(4)
        ->and(collect($all['rows'])->pluck('coupons', 'key')->all())->toBe([
            'active' => 1,
            'scheduled' => 0,
            'used_up' => 1,
            'expired' => 0,
            'inactive' => 1,
        ]);

    $either = $service->summarizeCouponReport([$holiday, $plain, $partner], [
        'statuses' => ['inactive'],
        'label_ids' => [5],
    ]);

    expect($either['filtered'])->toBeTrue()
        ->and($either['total_coupons'])->toBe(2)
        ->and($either['successful_redemptions'])->toBe(3);

    $uncategorized = $service->summarizeCouponReport([$holiday, $plain, $partner], [
        'uncategorized' => true,
    ]);

    expect($uncategorized['total_coupons'])->toBe(1)
        ->and(collect($uncategorized['rows'])->firstWhere('key', 'inactive')['coupons'])->toBe(1);
});

test('deleting a coupon label clears the category and keeps the coupon', function () {
    CourseCouponLabel::query()->create([
        'id' => 1,
        'name' => 'Holiday Sale',
        'color' => '#ca8a04',
    ]);

    CourseCoupon::query()->create([
        'code' => 'SAVE10',
        'course_coupon_label_id' => 1,
        'is_active' => true,
        'discount_type' => 'percentage',
        'discount' => 10,
    ]);

    CourseCouponLabel::query()->find(1)->delete();

    $coupon = CourseCoupon::query()->where('code', 'SAVE10')->first();

    expect($coupon)->not->toBeNull()
        ->and($coupon->course_coupon_label_id)->toBeNull()
        ->and($coupon->discount)->toEqual(10);
});

test('a trainer report includes only that trainer coupons and counts stored redemptions', function () {
    $now = now();
    $trainer = User::query()->create([
        'name' => 'Trainer',
        'email' => 'trainer@example.com',
        'role' => 'instructor',
        'instructor_id' => 10,
    ]);
    Auth::login($trainer);

    DB::table('courses')->insert([
        ['id' => 1, 'title' => 'Alpha', 'instructor_id' => 10, 'created_at' => $now, 'updated_at' => $now],
        ['id' => 2, 'title' => 'Beta', 'instructor_id' => 11, 'created_at' => $now, 'updated_at' => $now],
    ]);

    CourseCoupon::query()->insert([
        [
            'code' => 'MINE',
            'course_id' => 1,
            'is_active' => true,
            'created_by' => $trainer->id,
            'used_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'code' => 'THEIRS',
            'course_id' => 2,
            'is_active' => true,
            'created_by' => 99,
            'used_count' => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    DB::table('payment_histories')->insert([
        ['coupon' => 'MINE', 'meta' => json_encode(['coupon_code' => 'MINE']), 'created_at' => $now, 'updated_at' => $now],
        ['coupon' => 'THEIRS', 'meta' => null, 'created_at' => $now, 'updated_at' => $now],
    ]);

    $report = (new CourseCouponService())->couponStatusReport([]);

    expect($report['total_coupons'])->toBe(1)
        ->and($report['successful_redemptions'])->toBe(1)
        ->and(collect($report['rows'])->firstWhere('key', 'active')['coupons'])->toBe(1);
});

test('the coupon list keeps only the selected status and category', function () {
    $admin = User::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'role' => 'admin',
    ]);
    Auth::login($admin);

    $scholarsLabel = CourseCouponLabel::query()->create(['name' => 'Scholars', 'color' => '#1A344F']);
    $holidayLabel = CourseCouponLabel::query()->create(['name' => 'Holiday Sale', 'color' => '#ca8a04']);

    DB::table('courses')->insert([
        'id' => 4,
        'title' => 'Estimating',
        'instructor_id' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    CourseCoupon::query()->create([
        'code' => 'SSA26MARK3',
        'discount_type' => 'percentage',
        'discount' => 100,
        'course_id' => 4,
        'course_coupon_label_id' => $scholarsLabel->id,
        'is_active' => true,
    ]);
    CourseCoupon::query()->create([
        'code' => 'SSA26MARK2',
        'discount_type' => 'percentage',
        'discount' => 100,
        'course_coupon_label_id' => $scholarsLabel->id,
        'is_active' => true,
        'usage_limit' => 1,
    ]);
    CourseCoupon::query()->create([
        'code' => 'HOLIDAY',
        'discount_type' => 'fixed',
        'discount' => 50,
        'course_coupon_label_id' => $holidayLabel->id,
        'is_active' => true,
    ]);
    CourseCoupon::query()->create([
        'code' => 'PLAIN',
        'discount_type' => 'percentage',
        'discount' => 10,
        'is_active' => false,
    ]);

    DB::table('payment_histories')->insert([
        'coupon' => 'SSA26MARK2',
        'meta' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = new CourseCouponService();
    $scholars = $service->getCouponsList([], false, ['label_ids' => [$scholarsLabel->id]]);
    $activeScholars = $service->getCouponsList(['search' => 'ssa', 'per_page' => 1, 'page' => 1], true, [
        'statuses' => ['active'],
        'label_ids' => [$scholarsLabel->id],
    ]);
    $uncategorized = $service->getCouponsList([], false, ['uncategorized' => true]);

    expect($scholars->pluck('code')->sort()->values()->all())->toBe(['SSA26MARK2', 'SSA26MARK3'])
        ->and($activeScholars->pluck('code')->all())->toBe(['SSA26MARK3'])
        ->and($activeScholars->total())->toBe(1)
        ->and($uncategorized->pluck('code')->all())->toBe(['PLAIN']);

    $csv = csvFromCouponExport($service->exportCouponsCsv(['search' => 'ssa'], ['label_ids' => [$scholarsLabel->id]]));
    $byCode = collect($csv)->skip(1)->keyBy(fn (array $row) => $row[0]);

    expect($csv[0])->toBe([
        'Coupon code',
        'Discount',
        'Course',
        'Allowed emails',
        'Usage',
        'Valid from',
        'Valid to',
        'Category',
        'Status',
    ])
        ->and($byCode->keys()->sort()->values()->all())->toBe(['SSA26MARK2', 'SSA26MARK3'])
        ->and($byCode['SSA26MARK3'][1])->toBe('100.00% OFF')
        ->and($byCode['SSA26MARK3'][2])->toBe('Estimating')
        ->and($byCode['SSA26MARK3'][3])->toBe('Any student')
        ->and($byCode['SSA26MARK3'][7])->toBe('Scholars')
        ->and($byCode['SSA26MARK3'][8])->toBe('Active')
        ->and($byCode['SSA26MARK2'][4])->toBe('1 / 1')
        ->and($byCode['SSA26MARK2'][8])->toBe('Used Up');

    $empty = csvFromCouponExport($service->exportCouponsCsv(['search' => 'missing'], ['label_ids' => [$scholarsLabel->id]]));

    expect($empty)->toHaveCount(1);

    expect(CourseCoupon::query()->pluck('discount', 'code')->all())->toMatchArray([
        'SSA26MARK3' => '100.00',
        'SSA26MARK2' => '100.00',
        'HOLIDAY' => '50.00',
        'PLAIN' => '10.00',
    ]);
});

function csvFromCouponExport(\Symfony\Component\HttpFoundation\StreamedResponse $response): array
{
    ob_start();
    $response->sendContent();
    $contents = ob_get_clean();
    $contents = preg_replace('/^\xEF\xBB\xBF/', '', (string) $contents) ?? '';
    $rows = [];

    foreach (preg_split("/\r\n|\n|\r/", trim($contents)) as $line) {
        if ($line === '') {
            continue;
        }

        $rows[] = str_getcsv($line);
    }

    return $rows;
}
