<?php

namespace App\Services;

use App\Enums\CandidateStatus;
use App\Enums\LearnerUserType;
use App\Services\Payment\PaymentRefundService;
use App\Models\Course\AssignmentSubmission;
use App\Models\Course\CourseEnrollment;
use App\Models\Course\QuizSubmission;
use App\Models\Course\SectionQuiz;
use App\Models\Course\WatchHistory;
use App\Models\User;
use App\Services\Course\CourseCompletionGateService;
use App\Services\Course\CoursePlayerService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Exam\Models\ExamAttempt;
use Modules\Exam\Models\ExamEnrollment;
use Modules\Exam\Services\ExamEnrollmentService;

class CandidatePipelineService
{
    public function __construct(
        private CoursePlayerService $coursePlayerService,
        private CourseCompletionGateService $courseCompletionGateService,
        private ExamEnrollmentService $examEnrollmentService,
        private PaymentRefundService $paymentRefundService,
    ) {}

    public function getCandidates(array $data = []): LengthAwarePaginator
    {
        $perPage = (int) ($data['per_page'] ?? 15);

        $candidates = User::with(['professionalType'])
            ->where('role', 'student')
            ->where('user_type', LearnerUserType::EXTERNAL)
            ->when(!empty($data['status']), function ($query) use ($data) {
                $query->where('candidate_status', $data['status']);
            })
            ->when(!empty($data['search']), function ($query) use ($data) {
                $query->where(function ($userQuery) use ($data) {
                    $userQuery->where('name', 'LIKE', '%' . $data['search'] . '%')
                        ->orWhere('email', 'LIKE', '%' . $data['search'] . '%');
                });
            })
            ->withCount([
                'courseEnrollments as paid_course_count' => function ($query) {
                    $query->where('enrollment_type', 'paid');
                },
                'examEnrollments as paid_exam_count' => function ($query) {
                    $query->where('enrollment_type', 'paid');
                },
            ])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $candidates->getCollection()->transform(function (User $user) {
            $cvMedia = $user->getFirstMedia('cv_resume');
            $user->cv_resume_url = $cvMedia ? $cvMedia->getFullUrl() : null;
            $user->cv_resume_name = $cvMedia ? $cvMedia->file_name : null;
            $user->has_cv = (bool) $cvMedia;

            return $user;
        });

        return $candidates;
    }

    public function getCandidateDetail(string|int $userId): array
    {
        $user = User::with(['professionalType'])
            ->where('role', 'student')
            ->where('user_type', LearnerUserType::EXTERNAL)
            ->findOrFail($userId);

        $cvMedia = $user->getFirstMedia('cv_resume');
        $user->cv_resume_url = $cvMedia ? $cvMedia->getFullUrl() : null;
        $user->cv_resume_name = $cvMedia ? $cvMedia->file_name : null;
        $user->has_cv = (bool) $cvMedia;

        return [
            'candidate' => $user,
            'paid_courses' => $this->getPaidCourseProgress($user),
            'paid_exams' => $this->getPaidExamProgress($user),
            'refundable_payments' => $user->candidate_status === CandidateStatus::HIRED
                ? $this->paymentRefundService->getRefundablePaymentsForUser($user->id)->values()->all()
                : [],
            'refund_attempts' => $this->paymentRefundService->getGatewayRefundAttemptsForUser($user->id)->values()->all(),
            'statuses' => collect(CandidateStatus::cases())->map(fn (CandidateStatus $status) => [
                'value' => $status->value,
                'label' => $status->getLabel(),
            ])->values()->all(),
        ];
    }

    public function updateStatus(string|int $userId, array $data): void
    {
        DB::transaction(function () use ($userId, $data) {
            User::query()
                ->where('id', $userId)
                ->where('role', 'student')
                ->where('user_type', LearnerUserType::EXTERNAL)
                ->update([
                    'candidate_status' => $data['candidate_status'],
                    'candidate_notes' => $data['candidate_notes'] ?? null,
                    'candidate_status_updated_at' => now(),
                ]);
        }, 5);
    }

    private function getPaidCourseProgress(User $user): array
    {
        $enrollments = CourseEnrollment::with([
            'course.assignments',
            'course.sections.section_lessons',
            'course.sections.section_quizzes',
        ])
            ->where('user_id', $user->id)
            ->where('enrollment_type', 'paid')
            ->orderByDesc('entry_date')
            ->get();

        if ($enrollments->isEmpty()) {
            return [];
        }

        $courseIds = $enrollments->pluck('course_id')->all();
        $watchHistories = WatchHistory::query()
            ->where('user_id', $user->id)
            ->whereIn('course_id', $courseIds)
            ->get()
            ->keyBy('course_id');

        $quizIds = $enrollments->flatMap(function (CourseEnrollment $enrollment) {
            return $enrollment->course->sections->flatMap->section_quizzes->pluck('id');
        })->unique()->values();

        $assignmentIds = $enrollments->flatMap(function (CourseEnrollment $enrollment) {
            return $enrollment->course->assignments->pluck('id');
        })->unique()->values();

        $quizSubmissions = QuizSubmission::query()
            ->where('user_id', $user->id)
            ->whereIn('section_quiz_id', $quizIds)
            ->get()
            ->groupBy('section_quiz_id');

        $assignmentSubmissions = AssignmentSubmission::query()
            ->where('user_id', $user->id)
            ->whereIn('course_assignment_id', $assignmentIds)
            ->orderByDesc('attempt_number')
            ->get()
            ->groupBy('course_assignment_id');

        return $enrollments->map(function (CourseEnrollment $enrollment) use (
            $user,
            $watchHistories,
            $quizSubmissions,
            $assignmentSubmissions,
        ) {
            $course = $enrollment->course;
            $watchHistory = $watchHistories->get($course->id);

            $completion = $watchHistory
                ? $this->coursePlayerService->calculateCompletion($course, $watchHistory)
                : $this->emptyCompletion($course);

            $courseGates = $this->courseCompletionGateService->getGateStatus($course, $user->id, $completion);

            $quizzes = SectionQuiz::query()
                ->where('course_id', $course->id)
                ->orderBy('id')
                ->get();

            $quizProgress = $quizzes->map(function (SectionQuiz $quiz) use ($quizSubmissions) {
                $submission = $quizSubmissions->get($quiz->id)?->sortByDesc('id')->first();

                return [
                    'title' => $quiz->title,
                    'attempted' => (bool) $submission,
                    'score' => $submission?->total_marks,
                    'is_passed' => $submission?->is_passed,
                ];
            });

            $assignments = $course->assignments->map(function ($assignment) use ($assignmentSubmissions) {
                $submission = $assignmentSubmissions->get($assignment->id)?->first();

                return [
                    'title' => $assignment->title,
                    'submitted' => (bool) $submission,
                    'status' => $submission?->status ?? 'not_submitted',
                    'marks_obtained' => $submission?->marks_obtained,
                ];
            });

            $passedQuizzes = $quizProgress->where('is_passed', true)->count();

            return [
                'enrollment_id' => $enrollment->id,
                'course_id' => $course->id,
                'course_title' => $course->title,
                'enrolled_at' => $enrollment->entry_date,
                'completion' => $completion,
                'course_gates' => $courseGates,
                'quizzes_passed' => $passedQuizzes,
                'quizzes_total' => $quizzes->count(),
                'assignments_submitted' => $assignments->where('submitted', true)->count(),
                'assignments_total' => $assignments->count(),
                'quizzes' => $quizProgress->values()->all(),
                'assignments' => $assignments->values()->all(),
            ];
        })->values()->all();
    }

    private function getPaidExamProgress(User $user): array
    {
        $enrollments = ExamEnrollment::with('exam')
            ->where('user_id', $user->id)
            ->where('enrollment_type', 'paid')
            ->orderByDesc('entry_date')
            ->get();

        return $enrollments->map(function (ExamEnrollment $enrollment) use ($user) {
            $exam = $enrollment->exam;
            $marks = $this->examEnrollmentService->calculateStudentExamMarks((string) $exam->id, (string) $user->id);
            $attemptCount = ExamAttempt::query()
                ->where('exam_id', $exam->id)
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->count();

            return [
                'enrollment_id' => $enrollment->id,
                'exam_id' => $exam->id,
                'exam_title' => $exam->title,
                'enrolled_at' => $enrollment->entry_date,
                'pass_mark' => $exam->pass_mark,
                'total_marks' => $marks['total_marks'],
                'best_marks' => $marks['best_marks'],
                'best_percentage' => $marks['best_percentage'],
                'grade' => $marks['grade'],
                'is_passed' => $marks['is_passed'],
                'attempt_count' => $attemptCount,
            ];
        })->values()->all();
    }

    private function emptyCompletion($course): array
    {
        $totalItems = 0;

        foreach ($course->sections as $section) {
            $totalItems += $section->section_lessons->count() + $section->section_quizzes->count();
        }

        return [
            'total_items' => $totalItems,
            'completed_items' => 0,
            'completion' => 0,
        ];
    }
}
