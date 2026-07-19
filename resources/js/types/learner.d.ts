interface ProfessionalDevelopmentGuide {
   id: number;
   key: string;
   title: string;
   content: string | null;
   is_published: boolean;
   sort: number;
   created_at?: string;
   updated_at?: string;
}

interface ProjectCategory {
   id: number;
   title: string;
   slug: string;
   projects_count?: number;
   created_at?: string;
   updated_at?: string;
}

interface ProjectSubmission {
   id: number;
   project_id: number;
   user_id: number;
   file: string | null;
   file_name: string | null;
   submitted_at: string | null;
   score: string | number | null;
   feedback: string | null;
   scored_by: number | null;
   scored_at: string | null;
   project?: ProjectItem | null;
   user?: { id: number; name: string; email?: string } | null;
   scorer?: { id: number; name: string } | null;
   created_at?: string;
   updated_at?: string;
}

interface ProjectItem {
   id: number;
   project_category_id: number | null;
   category?: ProjectCategory | null;
   title: string;
   description: string | null;
   file: string | null;
   file_name: string | null;
   is_completed: boolean;
   is_published?: boolean;
   created_at?: string;
   updated_at?: string;
}

interface Announcement {
   id: number;
   title: string;
   body: string;
   is_published: boolean;
   user_id: number | null;
   author?: { id: number; name: string } | null;
   created_at?: string;
   updated_at?: string;
}

interface LearnerCertificate {
   id: number;
   type: 'course' | 'exam';
   identifier: string | null;
   certificate_id: string | null;
   verification_reference: string | null;
   title: string | null;
   issued_at: string | null;
   course_id: number | null;
   exam_id: number | null;
   training_hours: string | null;
   instructor_name: string | null;
}

interface LearnerResource {
   id: number;
   type: string;
   title: string;
   description: string | null;
   file: string | null;
   file_name: string | null;
   link: string | null;
   created_at?: string;
   updated_at?: string;
}

interface HelpArticle {
   id: number;
   category: string;
   title: string;
   slug: string;
   body: string | null;
   video_url: string | null;
   video: string | null;
   video_name: string | null;
   file: string | null;
   file_name: string | null;
   is_published: boolean;
   sort_order: number;
   created_at?: string;
   updated_at?: string;
}
