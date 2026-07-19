<?php

namespace App\Services;

use App\Models\LearnerResource;
use App\Models\User;
use App\Models\HelpCenterArticle;
use App\Models\ProfessionalDevelopmentGuide;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Course\AssignmentSubmission;
use App\Models\Course\Course;
use App\Models\Course\CourseAssignment;
use App\Models\Course\CourseCart;
use App\Models\Course\CourseCertificate;
use App\Models\Course\CourseEnrollment;
use App\Models\Course\CourseLiveClass;
use App\Models\Course\CourseSection;
use App\Models\Course\QuizSubmission;
use App\Models\Course\SectionQuiz;
use App\Models\Course\WatchHistory;
use App\Services\MediaService;
use App\Services\Course\CommunityDiscussionService;
use App\Services\Course\CourseEnrollmentService;
use App\Services\Course\CourseFinalExamService;
use App\Services\Course\CoursePlayerService;
use App\Services\Course\CourseWishlistService;
use App\Services\Payment\StripeCustomerService;
use App\Services\Payment\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use Modules\Certificate\Models\CertificateTemplate;
use Modules\Certificate\Models\MarksheetTemplate;
use Modules\Exam\Services\ExamEnrollmentService;
use Modules\Exam\Services\ExamWishlistService;
use Modules\Exam\Services\ExamAttemptService;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamEnrollment;
use Modules\Exam\Models\ExamAttempt;
use Modules\Exam\Services\ExamResourceService;
use Modules\Certificate\Services\CertificateService;

class StudentService extends MediaService
{
   public function __construct(
      private InstructorService $instructor,
      private CourseEnrollmentService $courseEnrollment,
      private CoursePlayerService $coursePlayer,
      private CourseWishlistService $courseWishlist,
      private ExamWishlistService $examWishlist,
      private ExamResourceService $examResource,
      private ExamEnrollmentService $examEnrollment,
      private ExamAttemptService $examAttempt,
      private CertificateService $certificate,
      private CourseFinalExamService $courseFinalExamService,
      private AnnouncementService $announcementService,
      private SubscriptionService $subscriptionService,
      private StripeCustomerService $stripeCustomer,
      private CommunityDiscussionService $communityDiscussion,
   ) {}

   function getCartCount(): int
   {
      $user = Auth::user();
      return CourseCart::where('user_id', $user->id)->count();
   }

   function getStudentData(?string $tab = 'courses', array $query = []): array
   {
      $props = [];
      $user = Auth::user();
      $instructor = $this->instructor->getInstructorByUserId($user->id);
      $props['instructor'] = $instructor;

      switch ($tab) {
         case 'home':
            $enrollments = $this->courseEnrollment->getEnrollments(['user_id' => $user->id]);
            $this->hydrateCourseEnrollments($enrollments, $user);

            $props['courseEnrollments'] = $enrollments;
            $props['examEnrollments'] = $this->examEnrollment->getEnrollments(['user_id' => $user->id]);
            $props['recentActivity'] = $this->getLearnerActivity($user);
            break;

         case 'courses':
            $enrollments = $this->courseEnrollment->getEnrollments(['user_id' => $user->id]);
            $this->hydrateCourseEnrollments($enrollments, $user);

            $props['courseEnrollments'] = $enrollments;
            break;

         case 'exams':
            $enrollments = $this->examEnrollment->getEnrollments(['user_id' => $user->id]);
            $props['examEnrollments'] = $enrollments;
            break;

         case 'professional-development':
            $props['guides'] = ProfessionalDevelopmentGuide::where('is_published', true)
               ->orderBy('sort')
               ->get();
            break;

         case 'project-library':
            $props['projects'] = Project::with('category')->where('is_published', true)->orderByDesc('created_at')->get();
            $props['projectCategories'] = ProjectCategory::withCount('projects')->orderBy('title')->get();
            $props['projectSubmissions'] = \App\Models\ProjectSubmission::where('user_id', $user->id)
               ->with(['project:id,title,project_category_id', 'project.category:id,title'])
               ->orderByDesc('submitted_at')
               ->get();
            break;

         case 'announcements':
            $props['announcements'] = $this->announcementService
               ->publishedForStudent($user)
               ->get();
            break;

         case 'community':
            $props = array_merge($props, $this->communityDiscussion->listForUser($user, $query));
            break;

         case 'resources':
            $props['resources'] = LearnerResource::orderByDesc('created_at')->get();
            break;

         case 'help-center':
            $props['helpArticles'] = HelpCenterArticle::where('is_published', true)
               ->orderBy('category')
               ->orderBy('sort_order')
               ->orderByDesc('created_at')
               ->get();
            break;

         case 'certificates':
            $courseCertificates = CourseCertificate::where('user_id', $user->id)
               ->with('course:id,title,training_hours,instructor_id', 'course.instructor:id,user_id', 'course.instructor.user:id,name')
               ->orderByDesc('created_at')
               ->get()
               ->map(fn (CourseCertificate $certificate) => [
                  'id' => $certificate->id,
                  'type' => 'course',
                  'identifier' => $certificate->identifier,
                  'certificate_id' => $certificate->certificate_id,
                  'verification_reference' => $certificate->verification_reference,
                  'course_id' => $certificate->course_id,
                  'exam_id' => null,
                  'title' => $certificate->course->title ?? null,
                  'training_hours' => $certificate->course->training_hours ?? null,
                  'instructor_name' => $certificate->course?->instructor?->user?->name ?? null,
                  'issued_at' => optional($certificate->created_at)->format('F j, Y'),
                  'issued_at_sort' => optional($certificate->created_at)?->timestamp ?? 0,
               ]);

            $examCertificates = ExamAttempt::where('user_id', $user->id)
               ->where('status', 'completed')
               ->where('is_passed', true)
               ->with('exam:id,title')
               ->orderByDesc('obtained_marks')
               ->get()
               ->unique('exam_id')
               ->map(function (ExamAttempt $attempt) {
                  $attempt = $this->examAttempt->ensureCertificateId(
                     $this->examAttempt->ensureTrackingReference($attempt)
                  );
                  $issuedAt = $attempt->end_time ?? $attempt->created_at;

                  return [
                     'id' => $attempt->id,
                     'type' => 'exam',
                     'identifier' => null,
                     'certificate_id' => $attempt->certificate_id,
                     'verification_reference' => $attempt->tracking_reference,
                     'course_id' => null,
                     'exam_id' => $attempt->exam_id,
                     'title' => $attempt->exam->title ?? null,
                     'training_hours' => null,
                     'instructor_name' => null,
                     'issued_at' => optional($issuedAt)->format('F j, Y'),
                     'issued_at_sort' => optional($issuedAt)?->timestamp ?? 0,
                  ];
               });

            $props['certificates'] = $courseCertificates
               ->concat($examCertificates)
               ->sortByDesc('issued_at_sort')
               ->values()
               ->map(fn (array $certificate) => collect($certificate)->except('issued_at_sort')->all());
            $props['certificateTemplate'] = $this->certificate->getActiveCertificateTemplate('course');
            $props['examCertificateTemplate'] = $this->certificate->getActiveCertificateTemplate('exam');
            break;

         case 'wishlist':
            $courseWishlists = $this->courseWishlist->getWishlists(['user_id' => $user->id]);
            $examWishlists = $this->examWishlist->getWishlists(['user_id' => $user->id]);
            $props['courseWishlists'] = $courseWishlists;
            $props['examWishlists'] = $examWishlists;
            break;

         case 'subscriptions':
            $props['subscriptions'] = $this->subscriptionService->listForUser($user);
            $props['canManageBilling'] = $this->subscriptionService->userCanManageBilling($user)
                && $this->stripeCustomer->isStripeActive();
            break;

         default:
            break;
      }

      return $props;
   }

   private function hydrateCourseEnrollments($enrollments, User $user): void
   {
      foreach ($enrollments as $enrollment) {
         $watch_history = $this->coursePlayer->getWatchHistory($enrollment->course_id, $user->id);
         $enrollment->watch_history = $watch_history;
         $enrollment->completion = $watch_history
            ? $this->coursePlayer->calculateCompletion($enrollment->course, $watch_history)
            : null;

         if ($enrollment->course?->final_exam_id && (float) ($enrollment->completion['completion'] ?? 0) >= 100) {
            $this->courseFinalExamService->ensureFinalExamEnrollment($enrollment->course, $user);
         }
      }
   }

   /**
    * Build a chronological activity log for a single learner, combining course,
    * exam, quiz and assignment events into one feed.
    */
   function getLearnerActivity(User $user, int $limit = 12): array
   {
      $activities = collect();

      // Course enrollments — "started" a course.
      CourseEnrollment::with('course:id,title')
         ->where('user_id', $user->id)
         ->latest('created_at')
         ->limit($limit)
         ->get()
         ->each(function (CourseEnrollment $enrollment) use ($activities) {
            $activities->push([
               'type' => 'course',
               'action' => 'Enrolled in course',
               'detail' => $enrollment->course?->title,
               'occurred_at' => optional($enrollment->created_at)->toIso8601String(),
            ]);
         });

      // Course progress — "opened/continued" a course.
      WatchHistory::with('course:id,title')
         ->where('user_id', $user->id)
         ->latest('updated_at')
         ->limit($limit)
         ->get()
         ->each(function (WatchHistory $history) use ($activities) {
            $isComplete = !empty($history->completion_date);
            $activities->push([
               'type' => 'course',
               'action' => $isComplete ? 'Completed course' : 'Continued course',
               'detail' => $history->course?->title,
               'occurred_at' => optional($history->completion_date ?? $history->updated_at)->toIso8601String(),
            ]);
         });

      // Certificates earned — "finished" a course.
      CourseCertificate::with('course:id,title')
         ->where('user_id', $user->id)
         ->latest('created_at')
         ->limit($limit)
         ->get()
         ->each(function (CourseCertificate $certificate) use ($activities) {
            $activities->push([
               'type' => 'certificate',
               'action' => 'Earned certificate',
               'detail' => $certificate->course?->title,
               'occurred_at' => optional($certificate->created_at)->toIso8601String(),
            ]);
         });

      // Quiz submissions.
      QuizSubmission::with('section_quiz:id,title')
         ->where('user_id', $user->id)
         ->latest('updated_at')
         ->limit($limit)
         ->get()
         ->each(function (QuizSubmission $submission) use ($activities) {
            $activities->push([
               'type' => 'quiz',
               'action' => $submission->is_passed ? 'Passed quiz' : 'Submitted quiz',
               'detail' => $submission->section_quiz?->title,
               'occurred_at' => optional($submission->updated_at)->toIso8601String(),
            ]);
         });

      // Assignment submissions.
      AssignmentSubmission::with('assignment:id,title')
         ->where('user_id', $user->id)
         ->latest('submitted_at')
         ->limit($limit)
         ->get()
         ->each(function (AssignmentSubmission $submission) use ($activities) {
            $activities->push([
               'type' => 'assignment',
               'action' => $submission->status === 'graded' ? 'Assignment graded' : 'Submitted assignment',
               'detail' => $submission->assignment?->title,
               'occurred_at' => optional($submission->submitted_at ?? $submission->updated_at)->toIso8601String(),
            ]);
         });

      // Exam enrollments — "started" an exam.
      ExamEnrollment::with('exam:id,title')
         ->where('user_id', $user->id)
         ->latest('created_at')
         ->limit($limit)
         ->get()
         ->each(function (ExamEnrollment $enrollment) use ($activities) {
            $activities->push([
               'type' => 'exam',
               'action' => 'Enrolled in exam',
               'detail' => $enrollment->exam?->title,
               'occurred_at' => optional($enrollment->created_at)->toIso8601String(),
            ]);
         });

      // Exam attempts — "took/finished" an exam.
      ExamAttempt::with('exam:id,title')
         ->where('user_id', $user->id)
         ->latest('updated_at')
         ->limit($limit)
         ->get()
         ->each(function (ExamAttempt $attempt) use ($activities) {
            $action = $attempt->is_passed
               ? 'Passed exam'
               : ($attempt->status === 'completed' ? 'Completed exam' : 'Started exam');

            $activities->push([
               'type' => 'exam',
               'action' => $action,
               'detail' => $attempt->exam?->title,
               'occurred_at' => optional($attempt->end_time ?? $attempt->updated_at ?? $attempt->start_time)->toIso8601String(),
            ]);
         });

      return $activities
         ->filter(fn (array $item) => !empty($item['occurred_at']) && !empty($item['detail']))
         ->sortByDesc('occurred_at')
         ->take($limit)
         ->values()
         ->all();
   }

   function updateProfile(array $data, string $id): User
   {
      $user = User::find($id);

      if (array_key_exists('photo', $data) && $data['photo']) {
         $data['photo'] = $this->addNewDeletePrev($user, $data['photo'], "profile");
      }

      $filteredData = array_filter($data, function ($value) {
         return $value !== null;
      });

      $user->update($filteredData);

      return $user;
   }

   public function getEnrolledCourse(string $id, User $user): Course
   {
      $enrollment = CourseEnrollment::where('user_id', $user->id)
         ->where('course_id', $id)
         ->first();

      if (!$enrollment) {
         throw new \Exception('You are not enrolled in this course');
      }

      return Course::with(['instructor:id,user_id', 'instructor.user:id,name,photo'])->find($id);
   }

   public function getCourseModules(string $course_id)
   {
      return CourseSection::where('course_id', $course_id)
         ->with([
            'section_lessons',
            'section_quizzes'
         ])->get();
   }

   public function getCourseLiveClasses(string $course_id)
   {
      return CourseLiveClass::where('course_id', $course_id)->get();
   }

   public function getCourseAssignments(string $course_id, User $user)
   {
      $assignments = CourseAssignment::with([
         'submissions' => function ($query) use ($user) {
            $query->where('user_id', $user->id);
         },
         'sampleDownloads' => function ($query) use ($user) {
            $query->where('user_id', $user->id);
         },
      ])
         ->where('course_id', $course_id)
         ->get();

      return $assignments->map(function ($assignment) {
         $assignment->setAttribute(
            'sample_downloaded',
            $assignment->sampleDownloads->isNotEmpty()
         );

         return $assignment;
      });
   }

   public function getCourseSectionQuizzes(string $course_id, User $user)
   {
      return CourseSection::where('course_id', $course_id)
         ->with([
            'section_quizzes' => function ($quiz) use ($user) {
               $quiz->with([
                  'quiz_submissions' => function ($query) use ($user) {
                     $query->where('user_id', $user->id);
                  },
                  'quiz_questions' => function ($question) use ($user) {
                     $question->with(['answers' => function ($answer) use ($user) {
                        $answer->where('user_id', $user->id);
                     }]);
                  }
               ]);
            }
         ])
         ->whereHas('section_quizzes')
         ->get();
   }

   public function getCourseLessonResources(string $course_id)
   {
      return CourseSection::where('course_id', $course_id)
         ->whereHas('section_lessons', function ($query) {
            $query->whereHas('resources');
         })
         ->with([
            'section_lessons' => function ($lesson) {
               $lesson->whereHas('resources')
                  ->select([
                     'id',
                     'title',
                     'course_id',
                     'course_section_id'
                  ])
                  ->with(['resources']);
            }
         ])
         ->get();
   }

   function getEnrolledCourseOverview(string $course_id, string $tab, User $user): array
   {
      return [
         'modules' => $tab === 'modules' ? $this->getCourseModules($course_id) : null,
         'assignments' => $tab === 'assignments' ? $this->getCourseAssignments($course_id, $user) : null,
         'quizzes' => $tab === 'quizzes' ? $this->getCourseSectionQuizzes($course_id, $user) : null,
         'resources' => $tab === 'resources' ? $this->getCourseLessonResources($course_id) : null,
         'certificateTemplate' => $tab === 'certificate' ? $this->certificate->getActiveCertificateTemplate('course') : null,
         'marksheetTemplate' => $tab === 'certificate' ? $this->certificate->getActiveMarksheetTemplate('course') : null,
         'studentMarks' => $tab === 'certificate' ? $this->calculateStudentMarks($course_id, $user->id) : null,
      ];
   }

   function calculateStudentMarks(string $course_id, string $user_id): array
   {
      // Calculate Assignment Marks
      $assignments = CourseAssignment::where('course_id', $course_id)
         ->with(['submissions' => function ($query) use ($user_id) {
            $query->where('user_id', $user_id)
               ->where('status', 'graded'); // Only count graded submissions
         }])
         ->get();

      $totalAssignmentMarks = 0;
      $obtainedAssignmentMarks = 0;

      foreach ($assignments as $assignment) {
         $totalAssignmentMarks += $assignment->total_mark;

         // Get the best submission (highest marks)
         $bestSubmission = $assignment->submissions->sortByDesc('marks_obtained')->first();
         if ($bestSubmission) {
            $obtainedAssignmentMarks += $bestSubmission->marks_obtained;
         }
      }

      $assignmentPercentage = $totalAssignmentMarks > 0
         ? round(($obtainedAssignmentMarks / $totalAssignmentMarks) * 100, 2)
         : 0;

      // Calculate Quiz Marks
      $quizzes = SectionQuiz::where('course_id', $course_id)
         ->with(['quiz_submissions' => function ($query) use ($user_id) {
            $query->where('user_id', $user_id);
         }])
         ->get();

      $totalQuizMarks = 0;
      $obtainedQuizMarks = 0;

      foreach ($quizzes as $quiz) {
         $totalQuizMarks += $quiz->total_mark;

         // Get the best submission (highest total_marks)
         $bestSubmission = $quiz->quiz_submissions->sortByDesc('total_marks')->first();
         if ($bestSubmission) {
            $obtainedQuizMarks += $bestSubmission->total_marks;
         }
      }

      $quizPercentage = $totalQuizMarks > 0
         ? round(($obtainedQuizMarks / $totalQuizMarks) * 100, 2)
         : 0;

      // Calculate Overall Percentage
      $overallPercentage = 0;
      $hasAssignments = $totalAssignmentMarks > 0;
      $hasQuizzes = $totalQuizMarks > 0;

      if ($hasAssignments && $hasQuizzes) {
         // If both exist, average them
         $overallPercentage = round(($assignmentPercentage + $quizPercentage) / 2, 2);
      } elseif ($hasAssignments) {
         $overallPercentage = $assignmentPercentage;
      } elseif ($hasQuizzes) {
         $overallPercentage = $quizPercentage;
      }

      // Determine Grade
      $grade = calculateGrade($overallPercentage);

      return [
         'assignment' => [
            'total' => $totalAssignmentMarks,
            'obtained' => $obtainedAssignmentMarks,
            'percentage' => $assignmentPercentage,
         ],
         'quiz' => [
            'total' => $totalQuizMarks,
            'obtained' => $obtainedQuizMarks,
            'percentage' => $quizPercentage,
         ],
         'overall' => [
            'percentage' => $overallPercentage,
            'grade' => $grade,
         ],
      ];
   }

   function getEnrolledExamTabProps(string $exam_id, string $tab, User $user): array
   {
      $data = [
         'result' => null,
         'resources' => null,
         'certificateTemplate' => null,
         'marksheetTemplate' => null,
         'studentMarks' => null,
      ];

      if ($tab === 'certificate') {
         $data['certificateTemplate'] = $this->certificate->getActiveCertificateTemplate('exam');
         $data['marksheetTemplate'] = $this->certificate->getActiveMarksheetTemplate('exam');
         $examMarks = $this->examEnrollment->calculateStudentExamMarks($exam_id, $user->id);
         $data['studentMarks'] = [
            'assignment' => [
               'total' => 0,
               'obtained' => 0,
               'percentage' => 0,
            ],
            'quiz' => [
               'total' => $examMarks['total_marks'],
               'obtained' => $examMarks['best_marks'],
               'percentage' => $examMarks['best_percentage'],
            ],
            'overall' => [
               'percentage' => $examMarks['best_percentage'],
               'grade' => $examMarks['grade'],
            ],
         ];
      }

      // if ($tab === 'resources') {
      //    $data['resources'] = $this->examResource->getExamResources($exam_id);
      // }

      return $data;
   }
}
