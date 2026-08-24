<?php

namespace App\Services\Course;

use App\Enums\CourseStatusType;
use App\Enums\EnrollmentAccessStatus;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\Instructor;
use App\Models\User;
use App\Notifications\CourseApprovalNotification;
use App\Services\MediaService;
use App\Services\Payment\LaunchOfferService;
use App\Support\Database\SsuAcademyTableRegistry;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CourseService extends MediaService
{
   public function __construct(
      private CourseFinalExamService $courseFinalExamService,
   ) {}

   function getCheckoutCourse(string $id): Course
   {
      return Course::where('id', $id)->first();
   }

   function createCourse(array $data): Course
   {
      // Do not mass-assign the UploadedFile onto thumbnail — upload and store a short URL after create.
      $course = Course::create([
         ...collect($data)->except(['thumbnail'])->all(),
         'slug' => Str::slug($data['title']),
         'user_id' => Auth::user()->id,
         'course_type' => 'general',
      ]);

      if (! empty($data['thumbnail'])) {
         $course->update([
            'thumbnail' => $this->addNewDeletePrev($course, $data['thumbnail'], 'thumbnail'),
         ]);
      }

      return $course;
   }

   function updateCourse(string $id, array $data): Course
   {
      $course = Course::findOrFail($id);
      $tab = (string) ($data['tab'] ?? '');

      switch ($tab) {
         case 'basic':
            $this->courseFinalExamService->assertValidFinalExamLink(
               $data['final_exam_id'] ?? null,
               $course,
            );

            $wasComingSoon = $course->isComingSoon();
            $status = $data['status'] ?? $course->status;

            if (! empty($data['launch_at'])) {
               $launchAt = Carbon::parse($data['launch_at']);

               if ($launchAt->isFuture() && in_array($status, [
                  CourseStatusType::DRAFT->value,
                  CourseStatusType::PENDING->value,
               ], true)) {
                  $status = CourseStatusType::UPCOMING->value;
               }
            }

            $previousLaunchAt = $course->launch_at?->toIso8601String();

            $course->fill(collect($data)->except(['tab'])->all());
            $course->status = $status;
            $course->slug = Str::slug($data['title']);
            $course->save();

            if ($previousLaunchAt !== $course->fresh()?->launch_at?->toIso8601String()) {
               $this->syncReservedSeatBalanceDates($course->fresh() ?? $course);
            }

            $this->notifyLaunchWaitlistIfPublished($course, $wasComingSoon, $status);
            break;

         case 'pricing':
            $course->forceFill([
               'pricing_type' => $data['pricing_type'],
               'billing_model' => $data['billing_model'] ?? 'one_time',
               'price' => $data['price'] ?? null,
               'discount' => (bool) ($data['discount'] ?? false),
               'discount_price' => $data['discount_price'] ?? null,
               'subscription_price' => $data['subscription_price'] ?? null,
               'expiry_type' => $data['expiry_type'],
               'expiry_duration' => $data['expiry_duration'] ?? null,
               'launch_offer_enabled' => (bool) ($data['launch_offer_enabled'] ?? false),
               'launch_offer_starts_at' => $data['launch_offer_starts_at'] ?? null,
               'launch_offer_ends_at' => $data['launch_offer_ends_at'] ?? null,
               'launch_list_price' => $data['launch_list_price'] ?? null,
               'launch_offer_price' => $data['launch_offer_price'] ?? null,
               'launch_deposit_amount' => $data['launch_deposit_amount'] ?? null,
               'launch_balance_amount' => $data['launch_balance_amount'] ?? null,
               'launch_balance_grace_days' => $data['launch_balance_grace_days'] ?? 5,
               'launch_subscription_trial_ends_at' => $data['launch_subscription_trial_ends_at'] ?? null,
               'launch_full_upfront_price' => $data['launch_full_upfront_price'] ?? null,
            ])->save();

            $this->syncReservedSeatBalanceDates($course->fresh() ?? $course);
            break;

         case 'info':
            // Info tab saves FAQs / requirements / outcomes through their own endpoints.
            break;

         case 'media':
            $media = [];

            if (array_key_exists('preview', $data)) {
               $media['preview'] = $data['preview'];
            }

            if (! empty($data['banner'])) {
               $media['banner'] = $this->addNewDeletePrev($course, $data['banner'], 'banner');
            }

            if (! empty($data['thumbnail'])) {
               $media['thumbnail'] = $this->addNewDeletePrev($course, $data['thumbnail'], 'thumbnail');
            }

            if ($media !== []) {
               $course->forceFill($media)->save();
            }
            break;

         case 'seo':
            $course->forceFill(collect($data)->only([
               'meta_title',
               'meta_keywords',
               'meta_description',
               'og_title',
               'og_description',
            ])->all())->save();
            break;

         case 'status':
            $wasComingSoon = $course->isComingSoon();
            $previousLaunchAt = $course->launch_at?->toIso8601String();
            $payload = collect($data)->only(['status', 'launch_at', 'feedback'])->all();

            if (($payload['status'] ?? null) === CourseStatusType::APPROVED->value && empty($payload['launch_at'])) {
               $payload['launch_at'] = null;
            }

            $course->forceFill($payload)->save();

            if ($previousLaunchAt !== $course->fresh()?->launch_at?->toIso8601String()) {
               $this->syncReservedSeatBalanceDates($course->fresh() ?? $course);
            }

            $this->notifyLaunchWaitlistIfPublished(
               $course,
               $wasComingSoon,
               $payload['status'] ?? $course->status,
            );

            if (array_key_exists('feedback', $data) && $data['feedback']) {
               $instructor = Instructor::find($course->instructor_id);
               $user = User::find($instructor->user_id);

               $user->notify(new CourseApprovalNotification($course, $data));
            }

            break;

         default:
            throw new \InvalidArgumentException("Unsupported course update tab [{$tab}].");
      }

      return $course->fresh() ?? $course;
   }

   /**
    * Keep reserved-seat balance open/deadline dates aligned with Basic-tab launch_at
    * (and current grace days) for every course.
    */
   private function syncReservedSeatBalanceDates(Course $course): void
   {
      $launchOffer = app(LaunchOfferService::class);

      if (! $course->launch_at && ! $launchOffer->isConfigured($course)) {
         return;
      }

      CourseEnrollment::query()
         ->where('course_id', $course->id)
         ->where('access_status', EnrollmentAccessStatus::RESERVED->value)
         ->whereNull('balance_paid_at')
         ->update([
            'balance_due_at' => $launchOffer->balanceDueAt($course),
            'balance_deadline_at' => $launchOffer->balanceDeadlineAt($course),
         ]);
   }

   private function notifyLaunchWaitlistIfPublished(Course $course, bool $wasComingSoon, string $newStatus): void
   {
      $fresh = $course->fresh();

      // Only email waitlist when the course actually leaves Coming Soon
      // (approved + launch date cleared or already past). Do not email while
      // status is approved but launch_at is still in the future.
      if (
         ! $fresh
         || ! $wasComingSoon
         || $newStatus !== CourseStatusType::APPROVED->value
         || $fresh->isComingSoon()
      ) {
         return;
      }

      app(CourseLaunchNotificationService::class)->notifyWaitlist($fresh);
   }

   function getCourses(array $data, ?User $user = null, bool $paginate = false): LengthAwarePaginator|Collection
   {
      $page = array_key_exists('per_page', $data) ? intval($data['per_page']) : 10;

      $courses = Course::with([
         'instructor.user',
         'course_category',
         'course_category_child',
         'sections.section_lessons',
         'sections.section_quizzes',
      ])
         ->withCount('reviews')
         ->withCount('enrollments')
         ->withAvg('reviews as average_rating', 'rating')
         ->when(array_key_exists('search', $data), function ($query) use ($data) {
            return $query->where('title', 'LIKE', '%' . $data['search'] . '%');
         })
         ->when(array_key_exists('category', $data) && $data['category'] !== 'all', function ($query) use ($data) {
            return $query->whereHas('course_category', function ($category) use ($data) {
               $category->where('slug', $data['category']);
            });
         })
         ->when(array_key_exists('category_child', $data) && $data['category_child'] && $data['category_child'] !== 'all', function ($query) use ($data) {
            return $query->whereHas('course_category_child', function ($child) use ($data) {
               $child->where('slug',  $data['category_child']);
            });
         })
         ->when(array_key_exists('status', $data) && $data['status'] !== 'all' && empty($data['catalog']), function ($query) use ($data) {
            return $query->where('status', $data['status']);
         })
         ->when(array_key_exists('level', $data) && $data['level'] !== 'all', function ($query) use ($data) {
            return $query->where('level', $data['level']);
         })
         ->when(array_key_exists('price', $data) && $data['price'] !== 'all', function ($query) use ($data) {
            return $query->where('pricing_type', $data['price']);
         })
         ->when(array_key_exists('language', $data) && $data['language'] !== 'all', function ($query) use ($data) {
            return $query->where('language', $data['language']);
         })
         ->when($user && $user->role === 'instructor', function ($query) use ($user) {
            return $query->where('instructor_id', $user->instructor_id);
         })
         ->when(!empty($data['catalog']), function ($query) use ($user) {
            return $query->listedInCatalog()->visibleInCatalog($user);
         })
         ->when(!empty($data['enrollment_open']), function ($query) {
            return $query->enrollmentOpen();
         })
         ->orderBy('created_at', 'desc');

      if ($paginate) {
         return $courses->paginate($page);
      }

      return $courses->get();
   }

   function getUserCourseById(string $id, User $user): ?Course
   {
      $course = Course::where('id', $id)->with([
         'faqs',
         'outcomes',
         'requirements',
         'instructor.user',
         'final_exam:id,title,slug',
         'live_classes',
         'assignments.submissions',
         'enrollments:id',
         'sections' => function ($query) use ($user) {
            $query->with([
               'section_lessons.resources',
               'section_quizzes' => function ($quizzes) use ($user) {
                  $quizzes->with([
                     'quiz_questions' => function ($questions) use ($user) {
                        $questions->with(['answers' => function ($answers) use ($user) {
                           $answers->when($user, function ($query) use ($user) {
                              $query->where('user_id', $user->id)
                                 ->latest()
                                 ->limit(1);
                           });
                        }]);
                     },
                     'quiz_submissions' => function ($submissions) use ($user) {
                        $submissions->when($user, function ($query) use ($user) {
                           $query->where('user_id', $user->id)
                              ->latest()
                              ->limit(1);
                        });
                     }
                  ]);
               },
            ]);
         },
      ])->first();

      return $course;
   }

   function getGuestCourseById(string $id): Course
   {
      $course = Course::where('id', $id)
         ->withCount('enrollments')
         ->withAvg('reviews as average_rating', 'rating')
         ->with([
            'faqs',
            'outcomes',
            'requirements',
            'sections' => function ($query) {
               $query->with([
                  'section_lessons',
                  'section_quizzes',
               ]);
            },
            'instructor' => function ($query) {
               $query->with([
                  'user',
                  'courses' => function ($query) {
                     $query->withCount('enrollments')
                        ->withCount('reviews')
                        ->withAvg('reviews as average_rating', 'rating');
                  }
               ])
                  ->withCount(['courses'])
                  ->tap(function ($query) {
                     $instructors = SsuAcademyTableRegistry::table('instructors');
                     $courseReviews = SsuAcademyTableRegistry::table('course_reviews');
                     $courses = SsuAcademyTableRegistry::table('courses');
                     $courseEnrollments = SsuAcademyTableRegistry::table('course_enrollments');

                     $query
                        ->selectRaw("(SELECT COUNT(*) FROM {$courseReviews}
                           INNER JOIN {$courses} ON {$courseReviews}.course_id = {$courses}.id
                           WHERE {$courses}.instructor_id = {$instructors}.id) as total_reviews_count")
                        ->selectRaw("(SELECT AVG(rating) FROM {$courseReviews}
                           INNER JOIN {$courses} ON {$courseReviews}.course_id = {$courses}.id
                           WHERE {$courses}.instructor_id = {$instructors}.id) as total_average_rating")
                        ->selectRaw("(SELECT COUNT(DISTINCT user_id) FROM {$courseEnrollments}
                           INNER JOIN {$courses} ON {$courseEnrollments}.course_id = {$courses}.id
                           WHERE {$courses}.instructor_id = {$instructors}.id) as total_enrollments_count");
                  });
            },
         ])->first();

      return $course;
   }

   function lastSectionLessonSort(Course $course): array
   {
      $maxSectionSort = $course->sections->max('sort') ?? 0;
      $maxLessonSort = $course->sections->flatMap->section_lessons->max('sort') ?? 0;

      return [
         'lastSectionSort' => $maxSectionSort,
         'lastLessonSort' => $maxLessonSort,
      ];
   }

   function getCourseEnroll(string $courseId): ?CourseEnrollment
   {
      $user = Auth::user();
      if ($user) {
         return CourseEnrollment::where('course_id', $courseId)
            ->where('user_id', $user->id)
            ->first();
      } else {
         return null;
      }
   }

   function deleteCourse(string $id): void
   {
      $course = Course::find($id);
      $course->delete();
   }

   /**
    * Validate if a course is ready for approval based on content completeness
    *
    * @param Course $course The course object to validate
    * @return array Contains validation result with counts and approval status
    */
   function validateCourseForApproval(Course $course): array
   {
      // Initialize counts
      $sectionsCount = 0;
      $lessonsCount = 0;
      $quizzesCount = 0;
      $totalContent = 0;

      // Check if course has required basic information
      $hasThumbnail = !empty($course->thumbnail);

      // Calculate content counts if sections are loaded
      if ($course->sections) {
         $sectionsCount = $course->sections->count();

         // Get lessons count
         $lessonsCount = $course->sections->reduce(function ($carry, $section) {
            return $carry + ($section->section_lessons ? $section->section_lessons->count() : 0);
         }, 0);

         // Get quizzes count
         $quizzesCount = $course->sections->reduce(function ($carry, $section) {
            return $carry + ($section->section_quizzes ? $section->section_quizzes->count() : 0);
         }, 0);

         $totalContent = $sectionsCount + $lessonsCount;
      }

      // Pre-publish content requirements have been removed: a course can be
      // published/approved at any time regardless of sections, lessons,
      // outcomes, requirements, or thumbnail. Counts are still returned so the
      // content summary can be displayed for reference.
      $hasOutcomes = $course->outcomes && $course->outcomes->count() > 0;
      $hasRequirements = $course->requirements && $course->requirements->count() > 0;

      return [
         'approve_able' => true,
         'counts' => [
            'sections_count' => $sectionsCount,
            'lessons_count' => $lessonsCount,
            'quizzes_count' => $quizzesCount,
            'total_content_count' => $totalContent
         ],
         'has_requirements' => [
            'thumbnail' => $hasThumbnail,
            'min_sections' => $sectionsCount >= 1,
            'min_lessons' => $lessonsCount >= 1,
            'min_content' => $totalContent >= 1,
            'outcomes' => $hasOutcomes,
            'requirements' => $hasRequirements
         ],
         'validation_messages' => []
      ];
   }
}
