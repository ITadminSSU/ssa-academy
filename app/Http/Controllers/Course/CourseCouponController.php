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
    * Get coupon usage details
    */
   public function usages(CourseCoupon $coupon)
   {
      $usages = $coupon->usages()
         ->with('user:id,name,email')
         ->select('id', 'user_id', 'coupon', 'amount', 'purchase_type', 'purchase_id', 'created_at')
         ->with('purchasable')
         ->latest()
         ->get()
         ->map(function ($usage) {
            return [
               'id' => $usage->id,
               'user_name' => $usage->user?->name ?? 'Deleted User',
               'user_email' => $usage->user?->email ?? '-',
               'course_title' => $usage->purchasable?->title ?? '-',
               'amount' => $usage->amount,
               'date' => $usage->created_at->format('M d, Y H:i'),
            ];
         });

      return response()->json([
         'usages' => $usages,
         'total' => $usages->count(),
      ]);
   }

   /**
    * Bulk import coupon codes (supports simple list or multi-column CSV)
    */
   public function import(Request $request)
   {
      $request->validate([
         'csv_content' => 'required|string',
         'import_mode' => 'required|in:simple,csv',
         'course_id' => 'nullable|exists:courses,id',
         'discount_type' => ['nullable', 'required_if:import_mode,simple', 'in:percentage,fixed'],
         'discount' => ['nullable', 'required_if:import_mode,simple', 'numeric', 'min:0'],
         'valid_from' => 'nullable|date',
         'valid_to' => ['nullable', 'date', 'after:valid_from'],
         'is_active' => 'nullable|boolean',
      ]);

      $content = $request->csv_content;
      $lines = array_filter(array_map('trim', preg_split('/\r?\n/', $content)), fn($l) => $l !== '');

      if (empty($lines)) {
         return back()->withErrors(['csv_content' => 'No data provided.']);
      }

      $now = now();
      $userId = $request->user()->id;
      $records = [];
      $allCodes = [];
      $errors = [];

      if ($request->import_mode === 'csv') {
         $header = str_getcsv(array_shift($lines));
         $header = array_map(fn($h) => strtolower(trim($h)), $header);

         $requiredColumns = ['code', 'discount_type', 'discount'];
         $missing = array_diff($requiredColumns, $header);
         if (!empty($missing)) {
            return back()->withErrors(['csv_content' => 'Missing required columns: ' . implode(', ', $missing)]);
         }

         $validCourseIds = \App\Models\Course\Course::pluck('id')->toArray();

         foreach ($lines as $i => $line) {
            $row = str_getcsv($line);
            if (count($row) !== count($header)) {
               $errors[] = "Row " . ($i + 2) . ": column count mismatch.";
               continue;
            }
            $data = array_combine($header, $row);
            $code = strtoupper(trim($data['code'] ?? ''));
            if (!$code) {
               $errors[] = "Row " . ($i + 2) . ": empty code.";
               continue;
            }
            if (!in_array($data['discount_type'] ?? '', ['percentage', 'fixed'])) {
               $errors[] = "Row " . ($i + 2) . ": invalid discount_type (must be percentage or fixed).";
               continue;
            }
            if (!is_numeric($data['discount'] ?? '') || floatval($data['discount']) < 0) {
               $errors[] = "Row " . ($i + 2) . ": invalid discount value.";
               continue;
            }

            $courseId = !empty($data['course_id']) ? intval($data['course_id']) : null;
            if ($courseId && !in_array($courseId, $validCourseIds)) {
               $errors[] = "Row " . ($i + 2) . ": invalid course_id ({$courseId}).";
               continue;
            }

            $allCodes[] = $code;
            $records[] = [
               'code' => $code,
               'course_id' => $courseId,
               'discount_type' => $data['discount_type'],
               'discount' => floatval($data['discount']),
               'valid_from' => !empty($data['valid_from']) ? $data['valid_from'] : null,
               'valid_to' => !empty($data['valid_to']) ? $data['valid_to'] : null,
               'is_active' => isset($data['is_active']) ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) : true,
               'created_by' => $userId,
               'created_at' => $now,
               'updated_at' => $now,
            ];
         }
      } else {
         // Simple mode: codes only, shared settings
         $codes = array_filter(
            array_map('trim', preg_split('/[\r\n,]+/', $content)),
            fn($code) => $code !== ''
         );
         $codes = array_unique($codes);
         $allCodes = array_map('strtoupper', $codes);

         foreach ($allCodes as $code) {
            $records[] = [
               'code' => $code,
               'course_id' => $request->course_id ?: null,
               'discount_type' => $request->discount_type,
               'discount' => $request->discount,
               'valid_from' => $request->valid_from,
               'valid_to' => $request->valid_to,
               'is_active' => $request->boolean('is_active', true),
               'created_by' => $userId,
               'created_at' => $now,
               'updated_at' => $now,
            ];
         }
      }

      if (empty($records)) {
         $msg = 'No valid coupon codes found.';
         if (!empty($errors)) $msg .= ' Errors: ' . implode(' ', array_slice($errors, 0, 5));
         return back()->withErrors(['csv_content' => $msg]);
      }

      // Filter out existing codes
      $existing = CourseCoupon::whereIn('code', $allCodes)->pluck('code')->toArray();
      $records = array_filter($records, fn($r) => !in_array($r['code'], $existing));

      if (empty($records)) {
         return back()->withErrors(['csv_content' => 'All coupon codes already exist.']);
      }

      CourseCoupon::insert(array_values($records));

      $imported = count($records);
      $skipped = count($existing);
      $message = "{$imported} coupon(s) imported successfully.";
      if ($skipped > 0) $message .= " {$skipped} duplicate(s) skipped.";
      if (!empty($errors)) $message .= " " . count($errors) . " row(s) had errors and were skipped.";

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
