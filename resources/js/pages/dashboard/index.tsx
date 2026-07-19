import SsuStatCard from '@/components/ssu-stat-card';
import { Card } from '@/components/ui/card';
import DashboardLayout from '@/layouts/dashboard/layout';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { Head } from '@inertiajs/react';
import { BookOpen, UserCheck, UserPlus, Users, Video } from 'lucide-react';
import { ReactNode, useMemo } from 'react';
import { Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import OpenForumQuestions from './partials/open-forum-questions';
import RecentStudentActivity from './partials/recent-student-activity';
import StudentProgressOverview from './partials/student-progress-overview';
import TopPerformersChart from './partials/top-performers-chart';

type StatisticsType = {
   courses: number;
   lessons: number;
   enrollments: number;
   students: number;
   instructors: number;
};

type CourseStatusDistributionType = Record<string, number>;

type TopPerformerPreview = {
   rank: number;
   name: string;
   photo: string | null;
   score: number;
   is_top_performer: boolean;
};

type StudentProgressOverviewType = {
   completed: number;
   in_progress: number;
   not_started: number;
   total: number;
};

type RecentActivityItem = {
   user_name: string;
   user_photo: string | null;
   action: string;
   detail: string | null;
   occurred_at: string;
};

export interface DashboardProps extends SharedData {
   statistics: StatisticsType;
   revenueData: Record<string, number>;
   courseStatusDistribution: CourseStatusDistributionType;
   topPerformers: TopPerformerPreview[];
   studentProgressOverview: StudentProgressOverviewType;
   recentStudentActivity: RecentActivityItem[];
   openForumQuestions?: number;
   forumPreview?: import('@/types/page').CommunityDiscussion[];
   forumQueueUrl?: string | null;
}

const Dashboard = (props: DashboardProps) => {
   const { statistics, courseStatusDistribution, translate, auth, openForumQuestions = 0, forumPreview = [], forumQueueUrl = null } = props;
   const { frontend, dashboard } = translate;
   const isAdmin = auth.user.role === 'admin';
   const showForumWidget = isAdmin || auth.user.role === 'instructor';

   const pieChartData = useMemo(() => {
      return Object.entries(courseStatusDistribution).map(([name, value]) => ({
         name,
         value,
      }));
   }, [courseStatusDistribution]);

   return (
      <div className="space-y-7">
         <Head title={frontend.dashboard} />

         <div className="ssu-surface-card p-6">
            <p className="ssu-kicker mb-2">Operations</p>
            <h1 className="font-display text-2xl font-semibold tracking-tight">{dashboard.welcome}</h1>
            <p className="text-muted-foreground mt-2 text-sm">
               {isAdmin ? (dashboard.welcome_subtitle_admin ?? dashboard.welcome_subtitle) : dashboard.welcome_subtitle}
            </p>
         </div>

         <div className={cn('grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3', isAdmin ? 'lg:grid-cols-5' : 'lg:grid-cols-4')}>
            <SsuStatCard title={frontend.courses} value={statistics.courses} toneIndex={0} icon={<BookOpen className="h-6 w-6" />} />
            <SsuStatCard title={frontend.lessons} value={statistics.lessons} toneIndex={1} icon={<Video className="h-6 w-6" />} />
            <SsuStatCard title={frontend.enrollment} value={statistics.enrollments} toneIndex={2} icon={<UserCheck className="h-6 w-6" />} />
            <SsuStatCard title={frontend.students} value={statistics.students} toneIndex={0} icon={<Users className="h-6 w-6" />} />
            {isAdmin && <SsuStatCard title={'Instructors'} value={statistics.instructors} toneIndex={1} icon={<UserPlus className="h-6 w-6" />} />}
         </div>

         <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <Card className="ssu-surface-card col-span-full p-6 lg:col-span-4">
               <h3 className="mb-4 text-lg font-medium">{frontend.course_status}</h3>

               <div className="flex items-center justify-center">
                  <ResponsiveContainer width="100%" height={300}>
                     <PieChart>
                        <Pie
                           data={pieChartData}
                           cx="50%"
                           cy="50%"
                           innerRadius={0}
                           outerRadius={80}
                           fill="#8884d8"
                           dataKey="value"
                           paddingAngle={0}
                           label={false}
                        >
                           {pieChartData.map((entry, index) => (
                              <Cell
                                 key={`cell-${index}`}
                                 fill={
                                    [
                                       'oklch(0.20 0.003 255)',
                                       'oklch(0.34 0.004 255)',
                                       'oklch(0.62 0.20 25)',
                                       'oklch(0.45 0.02 255)',
                                       'oklch(0.55 0.01 255)',
                                    ][index % 5]
                                 }
                              />
                           ))}
                        </Pie>
                        <Legend layout="horizontal" align="center" verticalAlign="bottom" iconType="circle" />
                        <Tooltip formatter={(value) => [value, frontend.courses]} />
                     </PieChart>
                  </ResponsiveContainer>
               </div>
            </Card>

            <div className="col-span-full lg:col-span-8">
               <TopPerformersChart />
            </div>
         </div>

         <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <RecentStudentActivity />
            <StudentProgressOverview />
         </div>

         {showForumWidget ? (
            <OpenForumQuestions
               openForumQuestions={openForumQuestions}
               forumPreview={forumPreview}
               forumQueueUrl={forumQueueUrl}
            />
         ) : null}
      </div>
   );
};

Dashboard.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default Dashboard;
