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

interface CourseStudentProgressRow {
   enrollment: CourseEnrollment;
   completion: CourseCompletion;
   course_gates: CourseGates;
   assignments: CourseStudentAssignmentProgress[];
   quizzes: CourseStudentQuizProgress[];
   exams: CourseStudentExamProgress[];
   best_quiz_percent: number | null;
   overall_score_percent: number | null;
}

type StudentProgressSortBy = 'name' | 'completion' | 'overall_score' | 'best_quiz';
