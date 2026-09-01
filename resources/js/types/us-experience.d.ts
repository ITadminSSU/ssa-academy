interface UsExperienceFileRef {
   file_url: string;
   file_name: string;
}

interface UsExperienceLineItem {
   key: string;
   item: string;
   unit: string;
   expected_qty: number;
   tolerance_override?: number | null;
   tolerance_override_mode?: 'percent' | 'absolute' | null;
}

interface UsExperiencePlan {
   id: number;
   course_id: number;
   group_name: string;
   group_description?: string | null;
   title: string;
   sort_order: number;
   pass_mark: number;
   max_attempts: number;
   published: boolean;
   drawings?: UsExperienceFileRef[] | null;
   blank_template_file_url?: string | null;
   blank_template_file_name?: string | null;
   answer_key_file_url?: string | null;
   answer_key_file_name?: string | null;
   line_items?: UsExperienceLineItem[] | null;
   parsed_at?: string | null;
   tutorial_video_url?: string | null;
   tutorial_video_name?: string | null;
   is_ready?: boolean;
   drawings_count?: number;
   line_count?: number;
   attempts_count?: number;
}

interface UsExperienceAttemptSummary {
   id: number;
   attempt_number: number;
   status: 'passed' | 'failed';
   marks_obtained?: number | null;
   lines_correct?: number | null;
   lines_total?: number | null;
   lines_percent?: number | null;
   submitted_at?: string | null;
   trainer_feedback?: string | null;
   takeoff_pdf_name?: string | null;
   boq_xlsx_name?: string | null;
   has_pdf?: boolean;
   has_excel?: boolean;
   grading_breakdown?: Array<{
      key: string;
      item: string;
      unit: string;
      expected_qty: number;
      submitted_qty?: number | null;
      within_tolerance: boolean;
      is_correct?: boolean;
      tolerance?: number;
      tolerance_percent?: number | null;
   }> | null;
}

interface UsExperienceStudentPlan {
   id: number;
   title: string;
   group_name: string;
   group_description?: string | null;
   sort_order: number;
   pass_mark: number;
   max_attempts: number;
   attempts_used: number;
   status: 'locked' | 'ongoing' | 'passed' | 'failed';
   unlocked: boolean;
   can_download: boolean;
   can_submit: boolean;
   accuracy?: number | null;
   latest_attempt?: UsExperienceAttemptSummary | null;
   attempts: UsExperienceAttemptSummary[];
   tutorial_video?: { url: string; name: string } | null;
}

interface UsExperienceStudentPayload {
   plans: UsExperienceStudentPlan[];
   can_use_files: boolean;
   can_see_scores: boolean;
   default_tolerance_percent: number;
   pass_mark_hint: number;
}

interface UsExperiencePublicGroup {
   group_name: string;
   group_description?: string | null;
   plans: { title: string }[];
}
