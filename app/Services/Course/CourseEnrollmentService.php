<?php

namespace App\Services\Course;

use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\Course\SectionLesson;
use App\Models\Course\SectionQuiz;
use App\Services\Course\CourseSectionService;
use App\Services\MediaService;
use App\Support\EnrollmentListBilling;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Carbon\Carbon;
use Modules\PaymentGateways\Models\PaymentHistory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseEnrollmentService extends MediaService
{
   function getEnrollmentById(int $id): ?CourseEnrollment
   {
      return CourseEnrollment::with(['user', 'course'])->find($id);
   }

   function getEnrollmentByCourseId(int $courseId, int $userId): ?CourseEnrollment
   {
      return CourseEnrollment::where('course_id', $courseId)->where('user_id', $userId)->first();
   }

   function getEnrollments(array $data, bool $paginate = false, bool $withBilling = false): LengthAwarePaginator|Collection
   {
      $page = array_key_exists('per_page', $data) ? intval($data['per_page']) : 10;

      $enrollments = CourseEnrollment::with([
            'user',
            'course.instructor.user',
            'course.final_exam:id,title,slug',
            ...($withBilling ? ['subscription'] : []),
         ])
         ->when(array_key_exists('search', $data) && filled($data['search']), function ($query) use ($data) {
            $search = trim((string) $data['search']);

            return $query->where(function ($outer) use ($search) {
               $outer->whereHas('user', function ($user) use ($search) {
                  $user->where(function ($inner) use ($search) {
                     $inner->where('name', 'LIKE', '%' . $search . '%')
                        ->orWhere('email', 'LIKE', '%' . $search . '%');
                  });
               })->orWhereHas('course', function ($course) use ($search) {
                  $course->where('title', 'LIKE', '%' . $search . '%');
               });
            });
         })
         ->when(array_key_exists('instructor_id', $data), function ($query) use ($data) {
            return $query->whereHas('course', function ($course) use ($data) {
               $course->where('instructor_id', $data['instructor_id']);
            });
         })
         ->when(array_key_exists('user_id', $data), function ($query) use ($data) {
            $query->where('user_id', $data['user_id']);
         })
         ->orderBy('created_at', 'desc');

      if ($paginate) {
         $result = $enrollments->paginate($page)->withQueryString();

         if ($withBilling) {
            $this->decorateListRows($result->getCollection());
         }

         return $result;
      }

      $rows = $enrollments->get();

      if ($withBilling) {
         $this->decorateListRows($rows);
      }

      return $rows;
   }

   public function exportEnrollmentsCsv(array $data): StreamedResponse
   {
      $enrollments = $this->getEnrollments($data, false, true);
      $filename = sprintf('course-enrollments-%s.csv', now()->format('Y-m-d'));

      return response()->streamDownload(function () use ($enrollments) {
         $handle = fopen('php://output', 'w');

         fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

         fputcsv($handle, [
            'Student name',
            'Email',
            'Course',
            'Coupon',
            'Enrolled date',
            'Course expiry',
            'Subscription status',
            'Subscription expiry',
         ]);

         foreach ($enrollments as $enrollment) {
            fputcsv($handle, [
               $enrollment->user?->name ?? '',
               $enrollment->user?->email ?? '',
               $enrollment->course?->title ?? '',
               $enrollment->coupon_code ?: '',
               optional($enrollment->entry_date)?->format('Y-m-d') ?? '',
               $enrollment->expiry_date
                  ? $enrollment->expiry_date->format('Y-m-d')
                  : 'Lifetime access',
               $enrollment->subscription_status_label ?? '',
               $enrollment->subscription_expires_at
                  ? \Carbon\Carbon::parse($enrollment->subscription_expires_at)->format('Y-m-d')
                  : '',
            ]);
         }

         fclose($handle);
      }, $filename, [
         'Content-Type' => 'text/csv; charset=UTF-8',
      ]);
   }

   /**
    * @param  Collection<int, CourseEnrollment>|SupportCollection<int, CourseEnrollment>  $enrollments
    */
   private function decorateListRows($enrollments): void
   {
      if ($enrollments->isEmpty()) {
         return;
      }

      $codes = $this->couponCodesFor($enrollments);

      foreach ($enrollments as $enrollment) {
         $key = $enrollment->user_id.':'.$enrollment->course_id;
         $summary = EnrollmentListBilling::subscriptionSummary($enrollment->subscription, $enrollment->course);

         $enrollment->setAttribute('coupon_code', $codes[$key] ?? null);
         $enrollment->setAttribute('subscription_status', $summary['status']);
         $enrollment->setAttribute('subscription_status_label', $summary['label']);
         $enrollment->setAttribute('subscription_expires_at', $summary['expires_at']);
         $enrollment->makeHidden(['subscription']);
      }
   }

   /**
    * @param  Collection<int, CourseEnrollment>|SupportCollection<int, CourseEnrollment>  $enrollments
    * @return array<string, string>
    */
   private function couponCodesFor($enrollments): array
   {
      $query = PaymentHistory::query()
         ->where('purchase_type', Course::class)
         ->where(function ($outer) use ($enrollments) {
            foreach ($enrollments as $enrollment) {
               $outer->orWhere(function ($inner) use ($enrollment) {
                  $inner->where('user_id', $enrollment->user_id)
                     ->where('purchase_id', $enrollment->course_id);
               });
            }
         })
         ->orderByDesc('id');

      $codes = [];

      foreach ($query->get(['id', 'user_id', 'purchase_id', 'coupon', 'meta']) as $payment) {
         $key = $payment->user_id.':'.$payment->purchase_id;

         if (isset($codes[$key])) {
            continue;
         }

         $code = EnrollmentListBilling::couponCodeFromPayment($payment);

         if ($code !== '') {
            $codes[$key] = $code;
         }
      }

      return $codes;
   }


   function createCourseEnroll(array $data, bool $allowBeforeLaunch = false, bool $sendWelcome = true): CourseEnrollment
   {
      $enrollment = DB::transaction(function () use ($data, $allowBeforeLaunch) {
         $courseId = $data['course_id'];
         $course = Course::findOrFail($data['course_id']);

         $isDepositReservation = ($data['access_status'] ?? null) === \App\Enums\EnrollmentAccessStatus::RESERVED->value
            || ($data['enrollment_type'] ?? null) === 'deposit';

         if (!$course->isEnrollmentOpen() && !isAdmin() && !$allowBeforeLaunch && !$isDepositReservation) {
            throw new \InvalidArgumentException('This course is not available for enrollment yet.');
         }

         $courseSectionService = new CourseSectionService();

         // Calculate expiry date based on duration from enrollment time
         $expiryDate = null;
         if ($course->expiry_type !== 'lifetime' && $course->expiry_duration) {
            // Parse duration string (e.g., "3 months", "1 year")
            $duration = $course->expiry_duration;
            $now = Carbon::now();

            // Extract number and unit from duration string
            if (preg_match('/^(\d+)\s+(month|months|year|years)$/i', $duration, $matches)) {
               $value = (int) $matches[1];
               $unit = strtolower($matches[2]);

               // Add duration to current time
               if (str_contains($unit, 'month')) {
                  $expiryDate = $now->addMonths($value)->format('Y-m-d H:i:s');
               } elseif (str_contains($unit, 'year')) {
                  $expiryDate = $now->addYears($value)->format('Y-m-d H:i:s');
               }
            }
         }

         $enrollment = CourseEnrollment::create([
            ...$data,
            'entry_date' => now(),
            'expiry_date' => $expiryDate
         ]);

         // Reserved deposit seats have no player access yet — skip watch history.
         if ($isDepositReservation) {
            return $enrollment;
         }

         $lessons = SectionLesson::query()->where('course_id', $courseId);
         if ($lessons->exists()) {
            $courseSectionService->initWatchHistory($courseId, 'lesson', $data['user_id']);
            return $enrollment;
         }

         $quizzes = SectionQuiz::query()->where('course_id', $courseId);
         if ($quizzes->exists()) {
            $courseSectionService->initWatchHistory($courseId, 'quiz', $data['user_id']);
            return $enrollment;
         }

         return $enrollment;
      }, 5);

      if ($sendWelcome) {
         try {
            app(CourseEnrollmentWelcomeMailService::class)->sendForEnrollment($enrollment->fresh(['user', 'course.instructor.user', 'course.course_category']) ?? $enrollment);
         } catch (\Throwable $exception) {
            Log::warning('Course enrollment welcome email failed', [
               'enrollment_id' => $enrollment->id ?? null,
               'user_id' => $enrollment->user_id ?? ($data['user_id'] ?? null),
               'course_id' => $enrollment->course_id ?? ($data['course_id'] ?? null),
               'error' => $exception->getMessage(),
            ]);
         }
      }

      return $enrollment;
   }

   function deleteEnrollment(string $id): void
   {
      $enrollment = CourseEnrollment::find($id);
      $enrollment->delete();
   }
}
