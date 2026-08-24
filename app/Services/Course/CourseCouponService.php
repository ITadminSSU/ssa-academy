<?php

namespace App\Services\Course;

use App\Models\Course\Course;
use App\Models\Course\CourseCoupon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
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

      $coupons = CourseCoupon::with('course:id,title')
         ->withCount('usages')
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
}
