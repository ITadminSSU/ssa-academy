import ButtonGradientPrimary from '@/components/button-gradient-primary';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { LearnerActivity, StudentDashboardProps } from '@/types/page';
import { Link, usePage } from '@inertiajs/react';
import { formatDistanceToNow } from 'date-fns';
import { Award, BookOpen, ClipboardList, FileQuestion, GraduationCap, ListChecks, PlayCircle } from 'lucide-react';
import type { ComponentType } from 'react';

const activityIcons: Record<LearnerActivity['type'], ComponentType<{ className?: string }>> = {
   course: BookOpen,
   exam: FileQuestion,
   quiz: ListChecks,
   assignment: ClipboardList,
   certificate: Award,
};

const Home = () => {
   const { courseEnrollments = [], examEnrollments = [], recentActivity = [], auth } = usePage<StudentDashboardProps>().props;

   // Enrolled courses that aren't finished yet (includes ones the learner enrolled in but hasn't opened yet).
   const activeEnrollments = courseEnrollments.filter((enrollment) => Number(enrollment.completion?.completion ?? 0) < 100);

   // Courses the learner has actually started watching.
   const inProgress = activeEnrollments.filter((enrollment) => enrollment.watch_history);

   const completedCount = courseEnrollments.filter((enrollment) => Number(enrollment.completion?.completion ?? 0) >= 100).length;

   const continueEnrollment = [...activeEnrollments].sort((a, b) => {
      const aTime = a.watch_history?.updated_at ? new Date(a.watch_history.updated_at).getTime() : 0;
      const bTime = b.watch_history?.updated_at ? new Date(b.watch_history.updated_at).getTime() : 0;
      return bTime - aTime;
   })[0];

   const continueHref = continueEnrollment
      ? continueEnrollment.watch_history
         ? route('course.player', {
              type: continueEnrollment.watch_history.current_watching_type,
              watch_history: continueEnrollment.watch_history.id,
              lesson_id: continueEnrollment.watch_history.current_watching_id,
           })
         : route('student.course.show', { id: continueEnrollment.course.id, tab: 'modules' })
      : '';

   const firstName = auth.user?.name?.split(' ')[0] ?? 'there';

   const stats = [
      { label: 'Enrolled Courses', value: courseEnrollments.length, Icon: GraduationCap },
      { label: 'In Progress', value: inProgress.length, Icon: BookOpen },
      { label: 'Completed', value: completedCount, Icon: Award },
      { label: 'Exams', value: examEnrollments.length, Icon: FileQuestion },
   ];

   return (
      <div className="space-y-8">
         <div>
            <h1 className="text-2xl font-bold tracking-tight">Welcome back, {firstName}!</h1>
            <p className="text-muted-foreground mt-1 text-sm">Here's a quick look at where you left off and your recent progress.</p>
         </div>

         <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
            {stats.map(({ label, value, Icon }) => (
               <Card key={label} className="border">
                  <CardContent className="flex items-center gap-4 p-4">
                     <div className="bg-primary/10 text-primary flex h-11 w-11 items-center justify-center rounded-lg">
                        <Icon className="h-5 w-5" />
                     </div>
                     <div>
                        <p className="text-2xl font-bold leading-none">{value}</p>
                        <p className="text-muted-foreground mt-1 text-xs">{label}</p>
                     </div>
                  </CardContent>
               </Card>
            ))}
         </div>

         {continueEnrollment && (
            <div className="space-y-3">
               <h2 className="text-lg font-semibold">
                  {continueEnrollment.watch_history ? 'Continue where you left off' : 'Start learning'}
               </h2>
               <Card className="from-primary/5 to-primary/10 overflow-hidden border bg-gradient-to-r">
                  <CardContent className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                     <img
                        src={continueEnrollment.course.thumbnail || '/assets/images/blank-image.jpg'}
                        alt={continueEnrollment.course.title}
                        className="h-28 w-full rounded-lg object-cover sm:w-48"
                        onError={(e) => {
                           (e.target as HTMLImageElement).src = '/assets/images/blank-image.jpg';
                        }}
                     />
                     <div className="flex-1 space-y-3">
                        <p className="font-semibold">{continueEnrollment.course.title}</p>
                        <div className="space-y-1.5">
                           <div className="text-muted-foreground flex items-center justify-between text-xs font-medium">
                              <span>Progress</span>
                              <span>{continueEnrollment.completion?.completion ?? 0}%</span>
                           </div>
                           <Progress value={Number(continueEnrollment.completion?.completion ?? 0)} className="h-1.5" />
                        </div>
                        <ButtonGradientPrimary asChild shadow={false} className="to-primary-light hover:to-primary-light h-9">
                           <Link href={continueHref}>
                              <PlayCircle className="h-4 w-4" />
                              {continueEnrollment.watch_history ? 'Continue Learning' : 'Start Learning'}
                           </Link>
                        </ButtonGradientPrimary>
                     </div>
                  </CardContent>
               </Card>
            </div>
         )}

         <div className="space-y-3">
            <h2 className="text-lg font-semibold">Recent activity</h2>
            <Card className="border">
               <CardContent className="p-0">
                  {recentActivity.length > 0 ? (
                     <ul className="divide-border divide-y">
                        {recentActivity.map((activity, index) => {
                           const Icon = activityIcons[activity.type] ?? BookOpen;

                           return (
                              <li
                                 key={`${activity.action}-${activity.occurred_at}-${index}`}
                                 className="flex items-start gap-3 p-4"
                              >
                                 <div className="bg-primary/10 text-primary flex h-9 w-9 shrink-0 items-center justify-center rounded-lg">
                                    <Icon className="h-4 w-4" />
                                 </div>
                                 <div className="min-w-0 flex-1">
                                    <p className="text-sm leading-snug">
                                       <span className="font-medium">{activity.action}</span>
                                       {activity.detail ? (
                                          <span className="text-muted-foreground"> — {activity.detail}</span>
                                       ) : null}
                                    </p>
                                    <p className="text-muted-foreground mt-0.5 text-xs">
                                       {formatDistanceToNow(new Date(activity.occurred_at), { addSuffix: true })}
                                    </p>
                                 </div>
                              </li>
                           );
                        })}
                     </ul>
                  ) : (
                     <div className="flex flex-col items-center justify-center gap-3 p-10 text-center">
                        <GraduationCap className="text-muted-foreground h-10 w-10" />
                        <p className="text-muted-foreground text-sm">
                           No activity yet. Enroll in a course or exam to get started.
                        </p>
                        <ButtonGradientPrimary asChild shadow={false} className="h-9">
                           <Link href={route('student.category.courses', { category: 'all' })}>Browse Courses</Link>
                        </ButtonGradientPrimary>
                     </div>
                  )}
               </CardContent>
            </Card>
         </div>
      </div>
   );
};

export default Home;
