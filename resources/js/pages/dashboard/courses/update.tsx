import Tabs from '@/components/tabs';
import { TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import DashboardLayout from '@/layouts/dashboard/layout';
import { DrywallLandingSettings } from '@/pages/drywall-estimating/types';
import { SharedData } from '@/types/global';
import { router } from '@inertiajs/react';
import {
   BookText,
   CircleDollarSign,
   ClipboardCheck,
   FilePenLine,
   FlaskConical,
   FolderInput,
   ImageIcon,
   Images,
   LayoutTemplate,
   Ruler,
   Settings,
   UserRound,
} from 'lucide-react';
import { ReactNode } from 'react';
import ActivitySubmissions from './partials/activity-submissions';
import Basic from './partials/basic';
import CourseUpdateHeader from './partials/course-update-header';
import Curriculum from './partials/curriculum';
import Hero from './partials/hero';
import Info from './partials/info';
import InstructorCard from './partials/instructor-card';
import Landing from './partials/landing';
import Media from './partials/media';
import Pricing from './partials/pricing';
import ReviewCarousel from './partials/review-carousel';
import SEO from './partials/seo';
import UsExperiencePlans from './partials/us-experience-plans';

export interface CourseUpdateProps extends SharedData {
   tab?: string;
   assignment?: string;
   course: Course;
   prices: string[];
   audiences: string[];
   lastSectionSort: number;
   lastLessonSort: number;
   statuses: string[];
   labels: string[];
   expiries: string[];
   categories: CourseCategory[];
   submissions: Pagination<AssignmentSubmission>;
   activitySubmissions?: Pagination<LessonActivitySubmission> | null;
   watchHistory: WatchHistory | null;
   approvalStatus: CourseApprovalValidation;
   zoomConfig: ZoomConfigFields;
   assignments: CourseAssignment[];
   instructors: Instructor[] | null;
   instructorExams?: Pick<Exam, 'id' | 'title' | 'slug'>[];
   billingModels?: { value: string; label?: string }[] | string[];
   stripeActive?: boolean;
   stripeSynced?: boolean;
   launchNotificationCount?: number;
   usExperiencePlans?: UsExperiencePlan[];
   usExperiencePlan?: UsExperiencePlan | null;
   usExperienceDefaultTolerancePercent?: number;
   hasUsExperiencePlans?: boolean;
   showUsExperience?: boolean;
   showLandingPage?: boolean;
   landing?: DrywallLandingSettings | null;
}

const Update = (props: CourseUpdateProps) => {
   const { tab, course, translate, showUsExperience = false, showLandingPage = false } = props;
   const { button } = translate;

   const tabs = [
      {
         name: button.curriculum,
         slug: 'curriculum',
         Icon: FilePenLine,
         Component: Curriculum,
      },
      {
         name: button.build_your_us_experience ?? 'Build Your US Experience',
         slug: 'us-experience',
         Icon: Ruler,
         Component: UsExperiencePlans,
      },
      {
         name: 'Activity Reviews',
         slug: 'activity-reviews',
         Icon: ClipboardCheck,
         Component: ActivitySubmissions,
      },
      {
         name: button.basic,
         slug: 'basic',
         Icon: Settings,
         Component: Basic,
      },
      {
         name: button.pricing,
         slug: 'pricing',
         Icon: CircleDollarSign,
         Component: Pricing,
      },
      {
         name: button.info,
         slug: 'info',
         Icon: BookText,
         Component: Info,
      },
      {
         name: button.media,
         slug: 'media',
         Icon: FolderInput,
         Component: Media,
      },
      {
         name: 'Public hero',
         slug: 'hero',
         Icon: ImageIcon,
         Component: Hero,
      },
      {
         name: 'Review carousel',
         slug: 'review-carousel',
         Icon: Images,
         Component: ReviewCarousel,
      },
      {
         name: 'Instructor card',
         slug: 'instructor-card',
         Icon: UserRound,
         Component: InstructorCard,
      },
      {
         name: button.seo,
         slug: 'seo',
         Icon: FlaskConical,
         Component: SEO,
      },
      {
         name: 'Landing page',
         slug: 'landing',
         Icon: LayoutTemplate,
         Component: Landing,
      },
   ].filter((item) => (item.slug !== 'us-experience' || showUsExperience) && (item.slug !== 'landing' || showLandingPage));

   const videoCopyStatus = course.video_copy_status;
   const videoCopyError = course.video_copy_error;

   return (
      <section className="space-y-8">
         <CourseUpdateHeader />

         {videoCopyStatus === 'failed' && videoCopyError ? (
            <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm whitespace-pre-line text-red-800">{videoCopyError}</div>
         ) : null}

         {videoCopyStatus === 'copying' ? (
            <div className="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
               Lesson videos are still copying onto this draft. You can leave this page. Refresh in a few minutes to see them. The original course is
               unchanged.
            </div>
         ) : null}

         <Tabs value={tab ?? tabs[0].slug} className="grid grid-rows-1 gap-5 md:grid-cols-4">
            <div className="col-span-full md:col-span-1">
               <TabsList className="horizontal-tabs-list space-y-1">
                  {tabs.map(({ name, slug, Icon }) => (
                     <TabsTrigger
                        key={slug}
                        value={slug}
                        className="horizontal-tabs-trigger"
                        onClick={() =>
                           router.get(
                              route('courses.edit', {
                                 course: course.id,
                                 tab: slug,
                              }),
                           )
                        }
                     >
                        <Icon className="h-4 w-4" />
                        <span>{name}</span>
                     </TabsTrigger>
                  ))}
               </TabsList>
            </div>

            <div className="col-span-full md:col-span-3">
               {tabs.map(({ slug, Component }) =>
                  (tab ?? tabs[0].slug) === slug ? (
                     <TabsContent key={slug} value={slug} className="m-0" forceMount>
                        <Component />
                     </TabsContent>
                  ) : null,
               )}
            </div>
         </Tabs>
      </section>
   );
};

Update.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default Update;
