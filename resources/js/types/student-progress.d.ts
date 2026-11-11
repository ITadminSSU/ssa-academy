interface CourseStudentAssignmentProgress {
   assignment_id: number;
   title: string;
   total_mark: number;
   pass_mark: number;
   submitted: boolean;
   status: 'not_submitted' | 'pending' | 'graded' | 'late' | 'resubmitted' | string;
   marks_obtained: number | null;
   is_passed: boolean | null;
}

interface CourseStudentQuizProgress {
   quiz_id: number;
   title: string;
   total_mark: number;
   pass_mark: number;
   attempted: boolean;
   attempts: number;
   score: number | null;
   is_passed: boolean | null;
}

interface CourseStudentExamProgress {
   exam_id: number;
   title: string;
   score: number;
   total_marks: number;
   pass_mark: number;
   is_passed: boolean;
   attempts: number;
}

interface CourseStudentUsExperienceProgress {
   plan_id: number;
   title: string;
   group_name?: string | null;
   attempts_used: number;
   max_attempts: number;
   best_percent: number | null;
   is_passed: boolean;
   status: 'not_started' | 'ongoing' | 'passed' | 'failed';
   latest_attempt_id: number | null;
}

interface CourseStudentPositionItem {
   id: string;
   type: 'lesson' | 'quiz' | string;
   title: string;
   status: 'finished' | 'watching' | 'not_started';
   current: boolean;
   video_percent: number | null;
}

interface CourseStudentPositionSection {
   section_id: string;
   section_title: string;
   items: CourseStudentPositionItem[];
}

interface CourseStudentPositionNowOn {
   section_title: string | null;
   item_title: string;
   item_type: 'lesson' | 'quiz' | string | null;
   video_percent: number | null;
   unavailable: boolean;
}

interface CourseStudentPositionFinished {
   section_title: string;
   item_title: string;
   item_type: 'lesson' | 'quiz' | string;
}

interface CourseStudentPosition {
   status: 'not_started' | 'in_progress' | 'finished';
   now_on: CourseStudentPositionNowOn | null;
   last_finished: CourseStudentPositionFinished | null;
   last_active_at: string | null;
   outline: CourseStudentPositionSection[];
}

interface CourseStudentProgressRow {
   enrollment: CourseEnrollment;
   completion: CourseCompletion;
   course_gates: CourseGates;
   assignments: CourseStudentAssignmentProgress[];
   quizzes: CourseStudentQuizProgress[];
   exams: CourseStudentExamProgress[];
   us_experience?: CourseStudentUsExperienceProgress[];
   best_quiz_percent: number | null;
   overall_score_percent: number | null;
   position?: CourseStudentPosition;
}

type StudentProgressSortBy = 'name' | 'completion' | 'overall_score' | 'best_quiz';
