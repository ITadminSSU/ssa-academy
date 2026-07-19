import TableFilter from '@/components/table/table-filter';
import TableFooter from '@/components/table/table-footer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { Link } from '@inertiajs/react';
import { ReactNode } from 'react';

interface TrackingSummary {
   total_courses: number;
   total_enrollments: number;
   pending_review: number;
   approved_courses: number;
}

interface CourseTrackingSummary {
   avg_completion_percent: number;
   assignment_slots: number;
   assignments_submitted: number;
   quiz_slots: number;
   quizzes_passed: number;
}

interface CourseWithTracking extends Course {
   tracking_summary?: CourseTrackingSummary;
}

interface Props extends SharedData {
   courses: Pagination<CourseWithTracking>;
   summary: TrackingSummary;
}

const Index = ({ courses, summary, translate, auth }: Props) => {
   const { dashboard, button, table } = translate;
   const isAdmin = auth.user.role === 'admin';
   const progressIndexRoute = isAdmin ? 'admin.student-progress.index' : 'student-progress.index';
   const progressShowRoute = isAdmin ? 'admin.student-progress.show' : 'student-progress.show';

   return (
      <div className="space-y-6">
         <div>
            <h1 className="text-2xl font-bold">{dashboard.trainer_tracking_dashboard ?? dashboard.student_progress}</h1>
            <p className="text-muted-foreground">
               {dashboard.trainer_tracking_description ??
                  'Enrollment lists, completion progress, assignment tallies, and quiz/exam pass-fail metrics.'}
            </p>
         </div>

         <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
               <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium">{dashboard.total_courses ?? 'Courses'}</CardTitle>
               </CardHeader>
               <CardContent className="text-2xl font-bold">{summary.total_courses}</CardContent>
            </Card>
            <Card>
               <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium">{dashboard.enrolled_students}</CardTitle>
               </CardHeader>
               <CardContent className="text-2xl font-bold">{summary.total_enrollments}</CardContent>
            </Card>
            <Card>
               <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium">{dashboard.approved_courses ?? 'Published'}</CardTitle>
               </CardHeader>
               <CardContent className="text-2xl font-bold">{summary.approved_courses}</CardContent>
            </Card>
            <Card>
               <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium">{dashboard.pending_review ?? 'Pending Review'}</CardTitle>
               </CardHeader>
               <CardContent className="text-2xl font-bold">{summary.pending_review}</CardContent>
            </Card>
         </div>

         <Card>
            <TableFilter
               data={courses}
               title={dashboard.enrollment_tracking ?? dashboard.course_list}
               globalSearch={true}
               tablePageSizes={[10, 15, 20, 25]}
               routeName={progressIndexRoute}
            />

            <Table>
               <TableHeader>
                  <TableRow>
                     <TableHead>{table.course_title}</TableHead>
                     <TableHead>{table.instructor}</TableHead>
                     <TableHead className="text-center">{dashboard.enrolled_students}</TableHead>
                     <TableHead>{dashboard.completion}</TableHead>
                     <TableHead>{dashboard.assignments}</TableHead>
                     <TableHead>{dashboard.quizzes}</TableHead>
                     <TableHead>{dashboard.status ?? 'Status'}</TableHead>
                     <TableHead className="text-end">{table.action}</TableHead>
                  </TableRow>
               </TableHeader>
               <TableBody>
                  {courses.data.length > 0 ? (
                     courses.data.map((course) => {
                        const tracking = course.tracking_summary;
                        return (
                           <TableRow key={course.id}>
                              <TableCell className="font-medium">{course.title}</TableCell>
                              <TableCell>{course.instructor?.user?.name ?? '—'}</TableCell>
                              <TableCell className="text-center">{course.enrollments_count ?? 0}</TableCell>
                              <TableCell>
                                 <div className="min-w-[120px] space-y-1">
                                    <span className="text-sm">{tracking?.avg_completion_percent ?? 0}%</span>
                                    <Progress value={tracking?.avg_completion_percent ?? 0} className="h-1.5" />
                                 </div>
                              </TableCell>
                              <TableCell className="text-sm">
                                 {tracking?.assignments_submitted ?? 0}/{tracking?.assignment_slots ?? 0}
                              </TableCell>
                              <TableCell className="text-sm">
                                 {tracking?.quizzes_passed ?? 0}/{tracking?.quiz_slots ?? 0} {dashboard.passed_label}
                              </TableCell>
                              <TableCell>
                                 <Badge variant={course.status === 'approved' ? 'default' : 'secondary'} className="capitalize">
                                    {course.status}
                                 </Badge>
                              </TableCell>
                              <TableCell className="text-end">
                                 <Button asChild size="sm" variant="outline">
                                    <Link href={route(progressShowRoute, course.id)}>{button.view_progress}</Link>
                                 </Button>
                              </TableCell>
                           </TableRow>
                        );
                     })
                  ) : (
                     <TableRow>
                        <TableCell colSpan={8} className="h-24 text-center">
                           {dashboard.no_results}
                        </TableCell>
                     </TableRow>
                  )}
               </TableBody>
            </Table>

            <TableFooter className="p-5 sm:p-7" routeName={progressIndexRoute} paginationInfo={courses} />
         </Card>
      </div>
   );
};

Index.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default Index;
