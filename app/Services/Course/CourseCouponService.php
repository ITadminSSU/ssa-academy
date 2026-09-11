<?php

namespace App\Services\Course;

use App\Enums\CourseBillingModel;
use App\Models\Course\Course;
use App\Models\Course\CourseCoupon;
use App\Services\Payment\LaunchOfferService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateways\Models\PaymentHistory;

class CourseCouponService
{
   public function getCoursesList(array $data): Collection
   {
      $user = Auth::user();

      return Course::select('id', 'title')
         ->when(!isAdmin(), function ($query) use ($user) {
            $query->where('instructor_id', $user->instructor_id);
         })
         ->when(isset($data['course_search']), function ($query) use ($data) {
            $query->where('title', 'like', '%' . $data['course_search'] . '%');
         })
         ->get();
   }

   public function getCourseValidCoupon(string $courseId, string $code, int|string|null $userId = null): ?CourseCoupon
   {
      return $this->resolveCouponForCheckout($courseId, $code, $userId)['coupon'];
   }

   /**
    * @return array{coupon: ?CourseCoupon, error: ?string}
    */
   public function resolveCouponForCheckout(string $courseId, string $code, int|string|null $userId = null): array
   {
      $code = strtoupper(trim($code));

      if ($code === '') {
         return ['coupon' => null, 'error' => null];
      }

      $coupon = CourseCoupon::query()
         ->whereRaw('LOWER(code) = ?', [strtolower($code)])
         ->first();

      if (! $coupon) {
         return ['coupon' => null, 'error' => 'Invalid coupon code.'];
      }

      if ($userId !== null && $coupon->isRedeemedByUser($userId)) {
         return [
            'coupon' => null,
            'error' => $this->alreadyUsedMessage($coupon, $userId, $courseId),
         ];
      }

      if (! $coupon->isValid()) {
         return ['coupon' => null, 'error' => 'This coupon is not valid or has expired.'];
      }

      if ($courseId !== '' && $coupon->course_id && (string) $coupon->course_id !== (string) $courseId) {
         return ['coupon' => null, 'error' => 'This coupon is not valid for this course.'];
      }

      return ['coupon' => $coupon, 'error' => null];
   }

   protected function alreadyUsedMessage(CourseCoupon $coupon, int|string $userId, string $courseId): string
   {
      $priorCourse = $this->priorRedemptionCourseForUser($coupon, $userId);

      if ($priorCourse && (string) $priorCourse->id !== (string) $courseId) {
         return 'You already used this voucher on "'.$priorCourse->title.'". Each student can only use a code once.';
      }

      if ($priorCourse) {
         return 'You have already used this voucher for this course.';
      }

      return 'You have already used this voucher.';
   }

   protected function priorRedemptionCourseForUser(CourseCoupon $coupon, int|string $userId): ?Course
   {
      $history = PaymentHistory::query()
         ->where('user_id', $userId)
         ->where('coupon', $coupon->code)
         ->where('purchase_type', Course::class)
         ->latest('id')
         ->first();

      if (! $history?->purchase_id) {
         return null;
      }

      return Course::query()->find($history->purchase_id);
   }

   public function getCourseValidCoupons(string $courseId): Collection
   {
      return CourseCoupon::query()
         ->isValid()
         ->where(function ($query) use ($courseId) {
            $query->whereNull('course_id')
               ->orWhere('course_id', $courseId);
         })
         ->get();
   }

   public function getCouponsList(array $data, bool $paginate = false): LengthAwarePaginator|Collection
   {
      $user = Auth::user();
      $page = array_key_exists('coupon_per_page', $data) ? intval($data['coupon_per_page']) : 10;

      $search = $data['coupon_search'] ?? $data['search'] ?? null;

      $prefix = CourseCoupon::query()->getConnection()->getTablePrefix();
      $couponsTable = $prefix.'course_coupons';

      $coupons = CourseCoupon::with('course:id,title')
         ->select('*')
         ->selectSub(function ($query) use ($couponsTable) {
            $query->from('payment_histories')
               ->selectRaw('count(*)')
               ->where(function ($inner) use ($couponsTable) {
                  $inner->whereRaw('LOWER(coupon) = LOWER(`'.$couponsTable.'`.`code`)')
                     ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(meta, "$.coupon_code"))) = LOWER(`'.$couponsTable.'`.`code`)');
               });
         }, 'usages_count')
         ->when(!isAdmin(), function ($query) use ($user) {
            $query->where(function ($scoped) use ($user) {
               $scoped->where(function ($global) use ($user) {
                  $global->whereNull('course_id')
                     ->where(function ($owner) use ($user) {
                        $owner->whereNull('created_by')
                           ->orWhere('created_by', $user->id);
                     });
               })->orWhereHas('course', function ($courseQuery) use ($user) {
                  $courseQuery->where('instructor_id', $user->instructor_id);
               });
            });
         })
         ->when(!empty($search), function ($query) use ($search) {
            $query->where('code', 'like', '%' . $search . '%');
         })
         ->when(isset($data['is_active']), function ($query) use ($data) {
            $query->where('is_active', $data['is_active']);
         })
         ->orderBy('created_at', 'desc');

      if ($paginate) {
         $result = $coupons->paginate($page);
         $result->getCollection()->transform(function (CourseCoupon $coupon) {
            $coupon->used_count = (int) ($coupon->usages_count ?? $coupon->used_count);

            return $coupon;
         });

         return $result;
      }

      return $coupons->get()->transform(function (CourseCoupon $coupon) {
         $coupon->used_count = (int) ($coupon->usages_count ?? $coupon->used_count);

         return $coupon;
      });
   }

   public function discountForAmount(CourseCoupon $coupon, float $subtotal): float
   {
      $subtotal = round(max(0, $subtotal), 2);
      $type = strtolower((string) $coupon->discount_type);
      $value = (float) $coupon->discount;

      $discount = in_array($type, ['percentage', 'percent', 'pct'], true)
         ? round(($subtotal * $value) / 100, 2)
         : round($value, 2);

      return min($discount, $subtotal);
   }

   /**
    * Display-only remaining discount for the catalog card. Never applied at checkout.
    *
    * @return array{
    *     balance_with_coupon: float,
    *     total_with_coupon: float,
    *     full_upfront_with_coupon: float,
    *     discount_amount: float
    * }
    */
   public function amountsForFixedOffRemaining(float $deposit, float $balance, float $fullUpfront, float $off): array
   {
      $deposit = round(max(0, $deposit), 2);
      $balance = round(max(0, $balance), 2);
      $fullUpfront = round(max(0, $fullUpfront), 2);
      $off = round(max(0, $off), 2);

      $balanceDiscount = min($off, $balance);
      $upfrontDiscount = min($off, $fullUpfront);
      $balanceWithCoupon = round(max(0, $balance - $balanceDiscount), 2);

      return [
         'balance_with_coupon' => $balanceWithCoupon,
         'total_with_coupon' => round($deposit + $balanceWithCoupon, 2),
         'full_upfront_with_coupon' => round(max(0, $fullUpfront - $upfrontDiscount), 2),
         'discount_amount' => $balanceDiscount,
      ];
   }

   /**
    * Display-only catalog amounts. Never applied at checkout.
    *
    * @return array{
    *     advertised: bool,
    *     kind: string,
    *     list_price: float,
    *     deposit_amount: float,
    *     balance_amount: float,
    *     balance_with_coupon: float,
    *     total_with_coupon: float,
    *     full_upfront_price: float,
    *     full_upfront_with_coupon: float,
    *     subscription_price: float,
    *     discount_amount: float,
    *     window_end: string|null
    * }|null
    */
   public function catalogPromoFor(Course $course, ?CourseCoupon $coupon = null): ?array
   {
      $launchOffer = app(LaunchOfferService::class);

      if ($launchOffer->isConfigured($course) && ! $launchOffer->isFullPricePeriod($course)) {
         return $this->launchWindowCatalogPromoFor($course, $coupon, $launchOffer);
      }

      return $this->pricedCatalogPromoFor($course);
   }

   public function featuredCatalogCouponFor(Course $course): ?CourseCoupon
   {
      if (! $this->catalogColumnExists()) {
         return null;
      }

      $coupons = CourseCoupon::query()
         ->isValid($course->id)
         ->where('show_on_catalog', true)
         ->where('course_id', $course->id)
         ->get();

      if ($coupons->isEmpty()) {
         return null;
      }

      $launchOffer = app(LaunchOfferService::class);
      $balance = $launchOffer->isConfigured($course)
         ? $launchOffer->balanceAmount($course)
         : (float) ($course->price ?? 0);

      return $coupons
         ->sortByDesc(fn (CourseCoupon $coupon) => $this->discountForAmount($coupon, $balance))
         ->first();
   }

   public function appendCatalogPromos(mixed $courses): void
   {
      $collection = $this->coursesCollection($courses);

      if ($collection->isEmpty()) {
         return;
      }

      $couponsByCourse = collect();

      if ($this->catalogColumnExists()) {
         $ids = $collection->pluck('id')->filter()->values();

         if ($ids->isNotEmpty()) {
            $couponsByCourse = CourseCoupon::query()
               ->isValid()
               ->where('show_on_catalog', true)
               ->whereIn('course_id', $ids)
               ->get()
               ->groupBy(fn (CourseCoupon $coupon) => (int) $coupon->course_id);
         }
      }

      $launchOffer = app(LaunchOfferService::class);

      foreach ($collection as $course) {
         if (! $course instanceof Course) {
            continue;
         }

         $featured = null;

         if (! $this->pricingCatalogPromoEnabled($course)) {
            $courseCoupons = $couponsByCourse->get((int) $course->id) ?? collect();
            $balance = $launchOffer->isConfigured($course)
               ? $launchOffer->balanceAmount($course)
               : (float) ($course->price ?? 0);
            $featured = $courseCoupons
               ->sortByDesc(fn (CourseCoupon $coupon) => $this->discountForAmount($coupon, $balance))
               ->first();
         }

         $course->setAttribute('catalog_promo', $this->catalogPromoFor($course, $featured));
      }
   }

   public function makeCatalogExclusive(CourseCoupon $coupon): void
   {
      if (! $coupon->show_on_catalog || ! $coupon->course_id) {
         return;
      }

      CourseCoupon::query()
         ->where('course_id', $coupon->course_id)
         ->where('id', '!=', $coupon->id)
         ->update(['show_on_catalog' => false]);
   }

   private function launchWindowCatalogPromoFor(Course $course, ?CourseCoupon $coupon, LaunchOfferService $launchOffer): ?array
   {
      $deposit = $launchOffer->depositAmount($course);
      $balance = $launchOffer->balanceAmount($course);
      $list = $launchOffer->listPrice($course);
      $fullUpfront = $launchOffer->fullUpfrontPrice($course);

      if ($this->pricingCatalogPromoEnabled($course)) {
         $amounts = $this->amountsForFixedOffRemaining(
            $deposit,
            $balance,
            $fullUpfront,
            (float) $course->catalog_coupon_off_remaining,
         );
      } else {
         $coupon ??= $this->featuredCatalogCouponFor($course);

         if (! $coupon) {
            return null;
         }

         $balanceDiscount = $this->discountForAmount($coupon, $balance);
         $upfrontDiscount = $this->discountForAmount($coupon, $fullUpfront);
         $balanceWithCoupon = round(max(0, $balance - $balanceDiscount), 2);
         $amounts = [
            'balance_with_coupon' => $balanceWithCoupon,
            'total_with_coupon' => round($deposit + $balanceWithCoupon, 2),
            'full_upfront_with_coupon' => round(max(0, $fullUpfront - $upfrontDiscount), 2),
            'discount_amount' => $balanceDiscount,
         ];
      }

      if ($amounts['balance_with_coupon'] >= $balance - 0.009 && $amounts['full_upfront_with_coupon'] >= $fullUpfront - 0.009) {
         return null;
      }

      return [
         'advertised' => true,
         'kind' => 'pre_register',
         'list_price' => round($list, 2),
         'deposit_amount' => round($deposit, 2),
         'balance_amount' => round($balance, 2),
         'balance_with_coupon' => $amounts['balance_with_coupon'],
         'total_with_coupon' => $amounts['total_with_coupon'],
         'full_upfront_price' => round($fullUpfront, 2),
         'full_upfront_with_coupon' => $amounts['full_upfront_with_coupon'],
         'subscription_price' => round($launchOffer->subscriptionPrice($course), 2),
         'discount_amount' => $amounts['discount_amount'],
         'window_end' => $launchOffer->windowEnd($course)->toIso8601String(),
      ];
   }

   private function pricedCatalogPromoFor(Course $course): ?array
   {
      if (! $this->pricingCatalogPromoEnabled($course)) {
         return null;
      }

      $billing = $this->billingModelValue($course);
      $off = (float) ($course->catalog_coupon_off_remaining ?? 0);

      if ($billing === CourseBillingModel::ONE_TIME->value) {
         return $this->pricedPromoPayload('one_time', (float) ($course->price ?? 0), 0.0, $off);
      }

      if ($billing === CourseBillingModel::UPFRONT_SUBSCRIPTION->value) {
         return $this->pricedPromoPayload(
            'upfront',
            (float) ($course->price ?? 0),
            (float) ($course->subscription_price ?? 0),
            $off,
         );
      }

      return null;
   }

   /**
    * @return array{
    *     advertised: bool,
    *     kind: string,
    *     list_price: float,
    *     deposit_amount: float,
    *     balance_amount: float,
    *     balance_with_coupon: float,
    *     total_with_coupon: float,
    *     full_upfront_price: float,
    *     full_upfront_with_coupon: float,
    *     subscription_price: float,
    *     discount_amount: float,
    *     window_end: string|null
    * }|null
    */
   private function pricedPromoPayload(string $kind, float $price, float $monthly, float $off): ?array
   {
      $price = round(max(0, $price), 2);
      $monthly = round(max(0, $monthly), 2);
      $off = round(max(0, $off), 2);
      $withCoupon = round(max(0, $price - min($off, $price)), 2);

      if ($price <= 0 || $withCoupon <= 0 || $withCoupon >= $price - 0.009) {
         return null;
      }

      return [
         'advertised' => true,
         'kind' => $kind,
         'list_price' => $price,
         'deposit_amount' => 0.0,
         'balance_amount' => $price,
         'balance_with_coupon' => $withCoupon,
         'total_with_coupon' => $withCoupon,
         'full_upfront_price' => $price,
         'full_upfront_with_coupon' => $withCoupon,
         'subscription_price' => $monthly,
         'discount_amount' => round($price - $withCoupon, 2),
         'window_end' => null,
      ];
   }

   private function billingModelValue(Course $course): string
   {
      $model = $course->billing_model;

      return $model instanceof CourseBillingModel
         ? $model->value
         : (string) ($model ?? CourseBillingModel::ONE_TIME->value);
   }

   private function pricingCatalogPromoEnabled(Course $course): bool
   {
      if (Schema::hasTable('courses') && ! Schema::hasColumn('courses', 'catalog_coupon_promo')) {
         return false;
      }

      return (bool) $course->catalog_coupon_promo
         && (float) ($course->catalog_coupon_off_remaining ?? 0) > 0;
   }

   private function catalogColumnExists(): bool
   {
      return Schema::hasTable('course_coupons')
         && Schema::hasColumn('course_coupons', 'show_on_catalog');
   }

   private function coursesCollection(mixed $courses): SupportCollection
   {
      if ($courses instanceof LengthAwarePaginator) {
         return $courses->getCollection();
      }

      if ($courses instanceof Course) {
         return collect([$courses]);
      }

      return collect($courses);
   }
}
