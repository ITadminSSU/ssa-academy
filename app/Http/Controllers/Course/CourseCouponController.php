<?php

namespace App\Http\Controllers\Course;

use Inertia\Inertia;
use Inertia\Response;
use App\Http\Controllers\Controller;
use App\Http\Requests\CourseCouponRequest;
use App\Models\Course\CourseCoupon;
use App\Services\Course\CourseCouponService;
use Illuminate\Http\Request;

class CourseCouponController extends Controller
{
   public function __construct(private CourseCouponService $courseCoupon) {}

   /**
    * Display a listing of coupons
    */
   public function index(Request $request): Response
   {
      $courses = $this->courseCoupon->getCoursesList($request->all(), true);
      $coupons = $this->courseCoupon->getCouponsList($request->all(), true);

      return Inertia::render('dashboard/courses/coupons/index', [
         'courses' => $courses,
         'coupons' => $coupons,
      ]);
   }

   /**
    * Store a newly created coupon
    */
   public function store(CourseCouponRequest $request)
   {
      $payload = $request->validated();
      $payload['course_id'] = $payload['course_id'] ?? null;
      $payload['created_by'] = $request->user()->id;

      CourseCoupon::create($payload);

      return redirect()
         ->route('course-coupons.index')
         ->with('success', 'Coupon created successfully.');
   }

   /**
    * Update the specified coupon
    */
   public function update(CourseCouponRequest $request, CourseCoupon $coupon)
   {
      $coupon->update($request->validated());

      return redirect()
         ->route('course-coupons.index')
         ->with('success', 'Coupon updated successfully.');
   }

   /**
    * Remove the specified coupon
    */
   public function destroy(CourseCoupon $coupon)
   {
      $coupon->delete();

      return redirect()
         ->route('course-coupons.index')
         ->with('success', 'Coupon deleted successfully.');
   }

   /**
    * Bulk import coupon codes
    */
   public function import(Request $request)
   {
      $request->validate([
         'codes' => 'required|string',
         'course_id' => 'nullable|exists:courses,id',
         'discount_type' => 'required|in:percentage,fixed',
         'discount' => 'required|numeric|min:0',
         'valid_from' => 'nullable|date',
         'valid_to' => 'nullable|date|after:valid_from',
         'is_active' => 'boolean',
      ]);

      $codes = array_filter(
         array_map('trim', preg_split('/[\r\n,]+/', $request->codes)),
         fn($code) => $code !== ''
      );

      $codes = array_unique($codes);

      if (empty($codes)) {
         return back()->withErrors(['codes' => 'No valid coupon codes provided.']);
      }

      $existing = CourseCoupon::whereIn('code', $codes)->pluck('code')->toArray();
      $newCodes = array_diff($codes, $existing);

      if (empty($newCodes)) {
         return back()->withErrors(['codes' => 'All coupon codes already exist.']);
      }

      $now = now();
      $records = [];

      foreach ($newCodes as $code) {
         $records[] = [
            'code' => strtoupper($code),
            'course_id' => $request->course_id ?: null,
            'discount_type' => $request->discount_type,
            'discount' => $request->discount,
            'valid_from' => $request->valid_from,
            'valid_to' => $request->valid_to,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => $request->user()->id,
            'created_at' => $now,
            'updated_at' => $now,
         ];
      }

      CourseCoupon::insert($records);

      $imported = count($newCodes);
      $skipped = count($existing);
      $message = "{$imported} coupon(s) imported successfully.";
      if ($skipped > 0) {
         $message .= " {$skipped} duplicate(s) skipped.";
      }

      return redirect()
         ->route('course-coupons.index')
         ->with('success', $message);
   }

   /**
    * Verify a coupon code
    */
   public function verify(Request $request)
   {
      $request->validate([
         'code' => 'required|string',
         'course_id' => 'nullable|exists:courses,id',
      ]);

      $query = CourseCoupon::where('code', $request->code);

      if ($request->has('course_id')) {
         $query->where(function ($q) use ($request) {
            $q->where('exam_id', $request->exam_id)
               ->orWhereNull('exam_id');
         });
      }

      $coupon = $query->first();

      if (!$coupon) {
         return response()->json([
            'valid' => false,
            'message' => 'Invalid coupon code.',
         ], 404);
      }

      if (!$coupon->isValid()) {
         return response()->json([
            'valid' => false,
            'message' => 'Coupon is not valid or has expired.',
         ], 400);
      }

      return response()->json([
         'valid' => true,
         'coupon' => $coupon,
      ]);
   }
}
