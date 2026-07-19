import { SharedData } from './global';

// pages/course-player
export interface CoursePlayerProps extends SharedData {
   type: string;
   course: Course;
   section: CourseSection;
   reviews: Pagination<CourseReview>;
   watching: SectionLesson | SectionQuiz;
   watchHistory: WatchHistory;
   courseGates: CourseGates;
   lessonWatchProgress?: { percent: number; max_seconds: number; duration_seconds: number } | null;
   totalContent: number;
   userReview: CourseReview | null;
   totalReviews: CourseTotalReview;
   zoomConfig: ZoomConfigFields;
   subscriptionAccess?: SubscriptionAccess;
}

// pages/intro
export interface IntroPageProps extends SharedData {
   page: Page;
   type: 'intro' | 'demo';
   customize: boolean;

   courses: Pagination<Course>;
   reviews: Pagination<CourseReview>;

   topCourse: Course;
   topCourses: Course[];
   newCourses: Course[];
   topReviews: CourseReview[];

   instructor: Instructor;
   instructors: Pagination<Instructor>;
   topInstructors: Instructor[];

   topCategories: CourseCategory[];
   categoryTopCourses: CourseCategory[];

   latestCourses: Course[];
   heroCourses: Course[];
   blogs: Blog[];
}

// pages/student/index
export interface StudentDashboardProps extends SharedData {
   tab: string;
   status?: string;
   instructor: Instructor;
   courseEnrollments?: CourseEnrollment[];
   examEnrollments?: ExamEnrollment[];
   courseWishlists?: CourseWishlist[];
   examWishlists?: ExamWishlist[];
   guides?: ProfessionalDevelopmentGuide[];
   projects?: ProjectItem[];
   projectCategories?: ProjectCategory[];
   projectSubmissions?: ProjectSubmission[];
   announcements?: Announcement[];
   resources?: LearnerResource[];
   helpArticles?: HelpArticle[];
   certificates?: LearnerCertificate[];
   certificateTemplate?: CertificateTemplate | null;
   examCertificateTemplate?: CertificateTemplate | null;
   recentActivity?: LearnerActivity[];
   hasVerifiedEmail: boolean;
   subscriptions?: UserSubscriptionSummary[];
   canManageBilling?: boolean;
   discussions?: CommunityDiscussion[];
   communityCourses?: Pick<Course, 'id' | 'title'>[];
   isTrainerView?: boolean;
   communityFilter?: 'all' | 'unanswered' | 'mine' | string;
   communityCourseId?: number | null;
}

export interface CommunityDiscussion {
   id: number;
   title: string;
   excerpt: string;
   created_at: string | null;
   replies_count: number;
   is_mine: boolean;
   needs_reply: boolean;
   has_instructor_reply: boolean;
   is_resolved: boolean;
   resolved_at: string | null;
   can_moderate: boolean;
   author: { id: number; name: string; photo: string | null } | null;
   course: { id: number; title: string; slug: string } | null;
   lesson: { id: number; title: string } | null;
   player: { watch_history_id: number; lesson_id: number } | null;
   pinned_reply: {
      id: number;
      description: string;
      author: { id: number; name: string; photo: string | null } | null;
   } | null;
}

export interface LearnerActivity {
   type: 'course' | 'exam' | 'quiz' | 'assignment' | 'certificate';
   action: string;
   detail: string | null;
   occurred_at: string;
}

export interface CourseGates {
   current_phase: 'video' | 'assignment' | 'quiz' | 'certification' | 'completed';
   has_video_lessons: boolean;
   has_assignments: boolean;
   has_quizzes: boolean;
   videos_completed: boolean;
   assignments_unlocked: boolean;
   assignments_submitted: boolean;
   assignments_approved: boolean;
   quizzes_unlocked: boolean;
   all_quizzes_passed: boolean;
   certificate_unlocked: boolean;
   pending_assignments_count: number;
}

export interface StudentCourseProps extends SharedData {
   tab: string;
   course: Course;
   modules: CourseSection[];
   live_classes?: CourseLiveClass[];
   assignments: CourseAssignment[];
   quizzes: CourseSection[];
   resources: CourseSection[];
   certificate: CourseCertificate | null;
   certificateTemplate: CertificateTemplate | null;
   marksheetTemplate: MarksheetTemplate | null;
   studentMarks: StudentMarks | null;
   watchHistory: WatchHistory;
   completion: CourseCompletion;
   courseGates: CourseGates;
   zoomConfig?: ZoomConfigFields;
   subscriptionAccess?: SubscriptionAccess;
}

export interface StudentExamProps extends SharedData {
   tab: string;
   exam: Exam;
   modules: any[];
   questions: ExamQuestion[];
   resources: any[];
   certificateTemplate: CertificateTemplate | null;
   marksheetTemplate: MarksheetTemplate | null;
   studentMarks: any | null;
   attempt: ExamAttempt;
   attempts: ExamAttempt[];
   bestAttempt: ExamAttempt | null;
   tutorialVideo?: { url: string; name: string } | null;
}

// pages/settings/pages
export interface PageSelectProps extends SharedData {
   pages: Page[];
   home: Settings<PageFields>;
   ssuLandingPage?: Page | null;
}

// pages/exams/show
export interface ExamPreviewProps extends SharedData {
   tab: string;
   exam: Exam;
   enrollment: ExamEnrollmentStatistics;
   reviews: Pagination<ExamReview>;
   wishlist: ExamWishlist;
   reviewsStatistics: ExamReviewsStatistics;
   instructor: Instructor;
}
