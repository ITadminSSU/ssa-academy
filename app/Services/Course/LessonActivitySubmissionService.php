<?php

namespace App\Services\Course;

use App\Models\Course\LessonActivitySubmission;
use App\Models\Course\SectionLesson;
use App\Models\Course\WatchHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LessonActivitySubmissionService
{
    public function __construct(private CoursePlayerService $coursePlayer) {}

    public function submit(array $data): LessonActivitySubmission
    {
        $lesson = SectionLesson::findOrFail($data['section_lesson_id']);

        if (!$lesson->requires_submission) {
            throw ValidationException::withMessages([
                'section_lesson_id' => 'This lesson does not accept submissions.',
            ]);
        }

        $userId = (int) $data['user_id'];
        $attemptNumber = LessonActivitySubmission::query()
            ->where('section_lesson_id', $lesson->id)
            ->where('user_id', $userId)
            ->max('attempt_number') ?? 0;

        $maxRetakes = (int) ($lesson->activity_retake ?: 1);
        if ($attemptNumber >= $maxRetakes) {
            throw ValidationException::withMessages([
                'section_lesson_id' => 'You have used all attempts for this activity.',
            ]);
        }

        return LessonActivitySubmission::create([
            ...$data,
            'attempt_number' => $attemptNumber + 1,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
    }

    public function grade(array $data, string $id): LessonActivitySubmission
    {
        $submission = LessonActivitySubmission::with('lesson')->findOrFail($id);
        $submission->update([
            ...$data,
            'grader_id' => Auth::id(),
        ]);

        $submission->refresh();

        if ($this->isApproved($submission, $submission->lesson)) {
            $this->markLessonCompleteForStudent($submission);
        }

        return $submission;
    }

    public function isApproved(LessonActivitySubmission $submission, SectionLesson $lesson): bool
    {
        if (in_array($submission->status, ['passed', 'approved'], true)) {
            return true;
        }

        if ($submission->status === 'graded' && $submission->marks_obtained !== null) {
            return (float) $submission->marks_obtained >= (float) ($lesson->activity_pass_mark ?? 0);
        }

        return false;
    }

    public function getSubmissionsForCourse(int $courseId, array $data = [])
    {
        $page = array_key_exists('per_page', $data) ? (int) $data['per_page'] : 10;

        return LessonActivitySubmission::with(['lesson', 'student', 'grader'])
            ->whereHas('lesson', fn ($query) => $query->where('course_id', $courseId))
            ->when(!empty($data['search']), function ($query) use ($data) {
                $query->whereHas('student', function ($q) use ($data) {
                    $q->where('name', 'LIKE', '%' . $data['search'] . '%')
                        ->orWhere('email', 'LIKE', '%' . $data['search'] . '%');
                });
            })
            ->orderByDesc('created_at')
            ->paginate($page);
    }

    private function markLessonCompleteForStudent(LessonActivitySubmission $submission): void
    {
        $lesson = $submission->lesson;
        if (!$lesson) {
            return;
        }

        $watchHistory = WatchHistory::query()
            ->where('course_id', $lesson->course_id)
            ->where('user_id', $submission->user_id)
            ->first();

        if ($watchHistory) {
            $this->coursePlayer->markItemComplete($watchHistory, $lesson->id, 'lesson');
        }
    }
}
