<?php

namespace App\Services\Course;

use App\Enums\CourseStatusType;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\StudentLabel;
use App\Models\Course\SectionLesson;
use App\Models\Course\SectionQuiz;
use App\Services\Course\CourseSectionService;
use App\Services\Integrations\GoHighLevelEnrollmentWebhook;
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

   /**
    * Enrollment rows per course for the course enrollment chart. A student in
    * two courses counts twice. Search and pagination are ignored so the chart
    * is not the current page. Tag ids limit the chart to students who have any
    * of those labels. A course id and a coupon keep the same rows as the list.
    *
    * @param  array{instructor_id?: int, user_id?: int, label_ids?: list<int>, course_id?: int, coupon?: string}  $scope
    * @return list<array{course: string, enrollments: int}>
    */
   public function enrollmentCountsByCourse(array $scope): array
   {
      $enrollments = (new CourseEnrollment())->getTable();
      $courses = (new Course())->getTable();
      $grammar = DB::connection()->getQueryGrammar();
      // Raw SQL does not receive the connection prefix. The live database
      // prefixes every table (ssa_academy_), so qualify these expressions
      // with the same wrapped names the query builder uses.
      $enrollmentsSql = $grammar->wrapTable($enrollments);
      $coursesSql = $grammar->wrapTable($courses);
      $enrollmentCount = 'COUNT('.$enrollmentsSql.'.'.$grammar->wrap('id').')';
      $courseTitle = 'MIN('.$coursesSql.'.'.$grammar->wrap('title').')';

      return DB::table($enrollments)
         ->join($courses, "{$courses}.id", '=', "{$enrollments}.course_id")
         ->when(array_key_exists('instructor_id', $scope), function ($query) use ($scope, $courses) {
            $query->where("{$courses}.instructor_id", $scope['instructor_id']);
         })
         ->when(array_key_exists('user_id', $scope), function ($query) use ($scope, $enrollments) {
            $query->where("{$enrollments}.user_id", $scope['user_id']);
         })
         ->when(! empty($scope['label_ids']), function ($query) use ($scope, $enrollments) {
            $this->whereStudentHasAnyLabel($query, $enrollments, $scope['label_ids']);
         })
         ->when(! empty($scope['course_id']), function ($query) use ($scope, $enrollments) {
            $query->where("{$enrollments}.course_id", (int) $scope['course_id']);
         })
         ->when(filled($scope['coupon'] ?? null), function ($query) use ($scope) {
            $this->whereLatestCoupon($query, (string) $scope['coupon']);
         })
         ->groupBy("{$courses}.id")
         ->selectRaw("{$courseTitle} as course_title")
         ->selectRaw("{$enrollmentCount} as enrollment_count")
         ->orderByRaw("{$enrollmentCount} DESC")
         ->orderByRaw("{$courseTitle} ASC")
         ->get()
         ->map(fn ($row) => [
            'course' => (string) $row->course_title,
            'enrollments' => (int) $row->enrollment_count,
         ])
         ->all();
   }

   /**
    * Ids of every enrollment matching the course list filters, including rows
    * on other pages. Used only to count a checkbox selection.
    *
    * @return list<int>
    */
   public function matchingEnrollmentIds(array $data): array
   {
      return $this->filteredEnrollments($data)
         ->orderBy('id')
         ->pluck('id')
         ->map(fn ($id) => (int) $id)
         ->all();
   }

   /**
    * Same filter and order as the course enrollment list, without loading relations.
    *
    * @return list<int>
    */
   public function listEnrollmentIds(array $data): array
   {
      $query = $this->filteredEnrollments($data);
      $this->applyListSort($query, $data);

      return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
   }

   private function filteredEnrollments(array $data)
   {
      $enrollments = (new CourseEnrollment())->getTable();

      return CourseEnrollment::query()
         ->when(array_key_exists('search', $data) && filled($data['search']), function ($query) use ($data) {
            $search = trim((string) $data['search']);

            return $query->where(function ($outer) use ($search) {
               $outer->whereHas('user', function ($user) use ($search) {
                  $user->where(function ($inner) use ($search) {
                     $inner->where('name', 'LIKE', '%'.$search.'%')
                        ->orWhere('email', 'LIKE', '%'.$search.'%');
                  });
               })->orWhereHas('course', function ($course) use ($search) {
                  $course->where('title', 'LIKE', '%'.$search.'%');
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
         ->when(! empty($data['label_ids']), function ($query) use ($data, $enrollments) {
            $this->whereStudentHasAnyLabel($query, $enrollments, $data['label_ids']);
         })
         ->when(! empty($data['course_id']), function ($query) use ($data, $enrollments) {
            $query->where("{$enrollments}.course_id", (int) $data['course_id']);
         })
         ->when(filled($data['coupon'] ?? null), function ($query) use ($data) {
            $this->whereLatestCoupon($query, (string) $data['coupon']);
         });
   }

   /**
    * Coupon codes used on enrollments in this scope, matching the code shown
    * on the list. The scope is the viewer, not the course or coupon filter.
    *
    * @param  array{instructor_id?: int, user_id?: int}  $scope
    * @return list<string>
    */
   public function couponCodesInScope(array $scope): array
   {
      $filters = array_filter(
         [
            'instructor_id' => $scope['instructor_id'] ?? null,
            'user_id' => $scope['user_id'] ?? null,
         ],
         static fn ($value) => $value !== null && $value !== '',
      );
      $enrollments = $this->filteredEnrollments($filters)->get(['user_id', 'course_id']);
      if ($enrollments->isEmpty()) {
         return [];
      }

      $codes = array_values(array_unique($this->couponCodesFor($enrollments)));
      sort($codes, SORT_STRING);

      return $codes;
   }

   /**
    * @param  \Illuminate\Database\Eloquent\Builder<CourseEnrollment>|\Illuminate\Database\Query\Builder  $query
    */
   private function whereLatestCoupon($query, string $coupon): void
   {
      $subquery = $this->couponSortQuery();
      $sql = "UPPER(TRIM(COALESCE((".$subquery->toSql()."), '')))";
      $target = $coupon === 'none' ? '' : strtoupper(trim($coupon));

      $query->whereRaw($sql.' = ?', [...$subquery->getBindings(), $target]);
   }

   /**
    * @param  \Illuminate\Database\Eloquent\Builder<CourseEnrollment>|\Illuminate\Database\Query\Builder  $query
    * @param  list<int>  $labelIds
    */
   private function whereStudentHasAnyLabel($query, string $enrollments, array $labelIds): void
   {
      $pivot = (new StudentLabel())->users()->getTable();
      $ids = array_values(array_filter(array_map('intval', $labelIds)));

      $query->whereExists(function ($sub) use ($pivot, $enrollments, $ids) {
         $sub->selectRaw('1')
            ->from($pivot)
            ->whereColumn("{$pivot}.user_id", "{$enrollments}.user_id")
            ->whereIn("{$pivot}.student_label_id", $ids);
      });
   }

   /**
    * @param  \Illuminate\Database\Eloquent\Builder<CourseEnrollment>  $query
    */
   private function applyListSort($query, array $data): void
   {
      $sort = $data['sort'] ?? null;
      $direction = ($data['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
      $enrollments = (new CourseEnrollment())->getTable();

      if ($sort === 'course') {
         $query->orderBy(
            Course::query()->select('title')->whereColumn('courses.id', "{$enrollments}.course_id")->limit(1),
            $direction,
         )->orderBy("{$enrollments}.id");

         return;
      }

      if ($sort === 'enrolled_date') {
         $query->orderBy('entry_date', $direction)->orderBy("{$enrollments}.id");

         return;
      }

      if ($sort === 'coupon') {
         $this->orderBySubquery($query, $this->couponSortQuery(), $direction)->orderBy("{$enrollments}.id");

         return;
      }

      if ($sort === 'tags') {
         $labels = (new StudentLabel())->getTable();
         $pivot = (new StudentLabel())->users()->getTable();
         $this->orderBySubquery(
            $query,
            StudentLabel::query()
               ->select("{$labels}.name")
               ->join($pivot, "{$pivot}.student_label_id", '=', "{$labels}.id")
               ->whereColumn("{$pivot}.user_id", "{$enrollments}.user_id")
               ->orderBy("{$labels}.name")
               ->limit(1),
            $direction,
         )->orderBy("{$enrollments}.id");

         return;
      }

      $query->orderBy('created_at', 'desc')->orderBy("{$enrollments}.id", 'desc');
   }

   /**
    * Empty coupons and students with no tag sort after the named values.
    *
    * @param  \Illuminate\Database\Eloquent\Builder<CourseEnrollment>  $query
    * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $subquery
    */
   private function orderBySubquery($query, $subquery, string $direction)
   {
      $sql = $subquery->toSql();

      return $query
         ->orderByRaw('('.$sql.') IS NULL', $subquery->getBindings())
         ->orderByRaw('('.$sql.') '.$direction, $subquery->getBindings());
   }

   private function couponSortQuery()
   {
      $payments = (new PaymentHistory())->getTable();
      $enrollments = (new CourseEnrollment())->getTable();
      $codeSql = DB::getDriverName() === 'sqlite'
         ? "COALESCE(NULLIF(coupon, ''), json_extract(meta, '$.coupon_code'))"
         : "COALESCE(NULLIF(`coupon`, ''), JSON_UNQUOTE(JSON_EXTRACT(`meta`, '$.coupon_code')))";

      return PaymentHistory::query()
         ->selectRaw($codeSql)
         ->where('purchase_type', Course::class)
         ->whereColumn("{$payments}.user_id", "{$enrollments}.user_id")
         ->whereColumn("{$payments}.purchase_id", "{$enrollments}.course_id")
         ->orderByDesc("{$payments}.id")
         ->limit(1);
   }

   function getEnrollments(array $data, bool $paginate = false, bool $withBilling = false): LengthAwarePaginator|Collection
   {
      $page = array_key_exists('per_page', $data) ? intval($data['per_page']) : 10;

      $enrollments = $this->filteredEnrollments($data)->with([
            'user.studentLabels',
            'course.instructor.user',
            'course.final_exam:id,title,slug',
            ...($withBilling ? ['subscription'] : []),
         ]);
      $this->applyListSort($enrollments, $data);

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
            'Student tags',
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
               $enrollment->user?->studentLabels?->pluck('name')->implode(', ') ?? '',
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

         if ($course->status === CourseStatusType::COMING_SOON->value && !isAdmin()) {
            throw new \InvalidArgumentException('This course is not available for enrollment yet.');
         }

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
         // Students who still owe the agreement also stay unstarted until they sign,
         // then the first Continue or Play creates watch history.
         if ($isDepositReservation || $this->shouldDeferCourseStart((int) $data['user_id'])) {
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

      try {
         app(GoHighLevelEnrollmentWebhook::class)->queueForEnrollment($enrollment);
      } catch (\Throwable) {
         Log::warning('Go High Level enrollment webhook failed', [
            'enrollment_id' => $enrollment->id ?? null,
            'http_status' => null,
         ]);
      }

      return $enrollment;
   }

   /**
    * A student who has not accepted the current agreement must not get watch history at enroll time.
    */
   private function shouldDeferCourseStart(int $userId): bool
   {
      $user = \App\Models\User::query()->find($userId);

      if (! $user || $user->role !== 'student') {
         return false;
      }

      return app(\App\Services\LegalAgreementService::class)->requiresAcceptance($user);
   }

   function deleteEnrollment(string $id): void
   {
      $enrollment = CourseEnrollment::find($id);
      $enrollment->delete();
   }
}
