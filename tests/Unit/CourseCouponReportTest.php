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
