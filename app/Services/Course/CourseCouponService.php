<?php

namespace App\Services\Course;

use App\Models\Course\Course;
use App\Models\Course\CourseCoupon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

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

   public function getCourseValidCoupon(string $courseId, string $code): ?CourseCoupon
   {
      return CourseCoupon::where('course_id', $courseId)
         ->where('code', $code)
         ->first();
   }

   public function getCourseValidCoupons(string $courseId): Collection
   {
      return CourseCoupon::where('course_id', $courseId)
         ->where('is_active', true)
         ->isValid()
         ->get();
   }

   public function getCouponsList(array $data, bool $paginate = false): LengthAwarePaginator|Collection
   {
      $user = Auth::user();
      $page = array_key_exists('coupon_per_page', $data) ? intval($data['coupon_per_page']) : 10;

      $search = $data['coupon_search'] ?? $data['search'] ?? null;

      $coupons = CourseCoupon::with('course:id,title')
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
         return $coupons->paginate($page);
      }

      return $coupons->get();
   }
}
