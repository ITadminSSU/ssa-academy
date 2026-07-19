import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import SsuStatCard from '@/components/ssu-stat-card';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import DashboardLayout from '@/layouts/dashboard/layout';
import { getQueryParams } from '@/lib/route';
import { SharedData } from '@/types/global';
import { Link, router, usePage } from '@inertiajs/react';
import { BookOpen, Clock, UserCheck, Users } from 'lucide-react';
import { ReactNode, useState } from 'react';

interface TrainerMetricRow {
   instructor: Instructor;
   courses_count: number;
   approved_courses: number;
   pending_review: number;
   enrollment_count: number;
   avg_completion_percent: number | null;
   assignment_submission_rate: number | null;
   quiz_pass_rate: number | null;
}

interface Props extends SharedData {
   trainers: TrainerMetricRow[];
   summary: {
      total_trainers: number;
      total_courses: number;
      pending_review_courses: number;
      total_enrollments: number;
      approved_courses: number;
   };
   sort_by: string;
   filters: { search: string };
}

const TrainerMetricsIndex = ({ trainers, summary, sort_by, filters, translate }: Props) => {
   const { dashboard } = translate;
   const page = usePage<SharedData>();
   const urlParams = getQueryParams(page.url);
   const [search, setSearch] = useState(filters.search ?? '');

   const handleSort = (value: string) => {
      router.get(route('admin.trainer-metrics.index', { ...urlParams, sort_by: value }), {}, { preserveState: true });
   };

   const handleSearch = (e: React.FormEvent) => {
      e.preventDefault();
      router.get(route('admin.trainer-metrics.index', { ...urlParams, search }), {}, { preserveState: true });
   };

   return (
      <div className="space-y-6">
         <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
               <h1 className="text-2xl font-bold">{dashboard.trainer_metrics_title ?? 'Trainer Metrics'}</h1>
               <p className="text-muted-foreground">
                  {dashboard.trainer_metrics_description ?? 'Master view of all trainer performance across the platform.'}
               </p>
            </div>
            {summary.pending_review_courses > 0 && (
               <Button asChild variant="outline">
                  <Link href={route('courses.index', { status: 'pending' })}>
                     {summary.pending_review_courses} {dashboard.pending_review ?? 'pending review'}
                  </Link>
               </Button>
            )}
         </div>

         <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <SsuStatCard title={dashboard.trainers ?? 'Trainers'} value={summary.total_trainers} toneIndex={0} icon={<Users className="h-6 w-6" />} />
            <SsuStatCard title={dashboard.total_courses ?? 'Courses'} value={summary.total_courses} toneIndex={1} icon={<BookOpen className="h-6 w-6" />} />
            <SsuStatCard title={dashboard.approved_courses ?? 'Published'} value={summary.approved_courses} toneIndex={2} icon={<UserCheck className="h-6 w-6" />} />
            <SsuStatCard title={dashboard.enrolled_students} value={summary.total_enrollments} toneIndex={0} icon={<Users className="h-6 w-6" />} />
            <SsuStatCard title={dashboard.pending_review ?? 'Pending Review'} value={summary.pending_review_courses} toneIndex={1} icon={<Clock className="h-6 w-6" />} />
         </div>

         <Card className="ssu-table-shell">
            <div className="flex flex-wrap items-center justify-between gap-4 border-b p-5">
               <form onSubmit={handleSearch} className="flex gap-2">
                  <Input
                     value={search}
                     onChange={(e) => setSearch(e.target.value)}
                     placeholder={dashboard.search_trainers ?? 'Search trainers...'}
                     className="w-[260px]"
                  />
                  <Button type="submit" variant="secondary">
                     {dashboard.search ?? 'Search'}
                  </Button>
               </form>
               <div className="flex items-center gap-2">
                  <span className="text-muted-foreground text-sm">{dashboard.sort_students_by}</span>
                  <Select value={sort_by} onValueChange={handleSort}>
                     <SelectTrigger className="w-[220px]">
                        <SelectValue />
                     </SelectTrigger>
                     <SelectContent>
                        <SelectItem value="enrollments">{dashboard.sort_by_enrollments ?? 'Most enrollments'}</SelectItem>
                        <SelectItem value="completion">{dashboard.sort_by_completion}</SelectItem>
                        <SelectItem value="courses">{dashboard.sort_by_courses ?? 'Most courses'}</SelectItem>
                        <SelectItem value="name">{dashboard.sort_by_name}</SelectItem>
                     </SelectContent>
                  </Select>
               </div>
            </div>

            <Table>
               <TableHeader>
                  <TableRow>
                     <TableHead>{dashboard.trainer ?? 'Trainer'}</TableHead>
                     <TableHead>{dashboard.courses ?? 'Courses'}</TableHead>
                     <TableHead>{dashboard.enrolled_students}</TableHead>
                     <TableHead>{dashboard.avg_completion ?? 'Avg Completion'}</TableHead>
                     <TableHead>{dashboard.assignments}</TableHead>
                     <TableHead>{dashboard.quizzes}</TableHead>
                     <TableHead>{dashboard.pending_review ?? 'Pending'}</TableHead>
                  </TableRow>
               </TableHeader>
               <TableBody>
                  {trainers.length > 0 ? (
                     trainers.map((row) => (
                        <TableRow key={row.instructor.id}>
                           <TableCell>
                              <p className="font-medium">{row.instructor.user?.name}</p>
                              <p className="text-muted-foreground text-xs">{row.instructor.user?.email}</p>
                           </TableCell>
                           <TableCell>
                              {row.approved_courses}/{row.courses_count}
                              <span className="text-muted-foreground text-xs"> {dashboard.published_label ?? 'published'}</span>
                           </TableCell>
                           <TableCell>{row.enrollment_count}</TableCell>
                           <TableCell>
                              <div className="min-w-[100px] space-y-1">
                                 <span className="text-sm">{row.avg_completion_percent ?? 0}%</span>
                                 <Progress value={row.avg_completion_percent ?? 0} className="h-1.5" />
                              </div>
                           </TableCell>
                           <TableCell>{row.assignment_submission_rate !== null ? `${row.assignment_submission_rate}%` : '—'}</TableCell>
                           <TableCell>
                              {row.quiz_pass_rate !== null ? (
                                 <Badge variant={row.quiz_pass_rate >= 70 ? 'default' : 'secondary'}>{row.quiz_pass_rate}% {dashboard.passed_label}</Badge>
                              ) : (
                                 '—'
                              )}
                           </TableCell>
                           <TableCell>
                              {row.pending_review > 0 ? (
                                 <Badge variant="outline">{row.pending_review}</Badge>
                              ) : (
                                 '—'
                              )}
                           </TableCell>
                        </TableRow>
                     ))
                  ) : (
                     <TableRow>
                        <TableCell colSpan={7} className="h-24 text-center">
                           {dashboard.no_results}
                        </TableCell>
                     </TableRow>
                  )}
               </TableBody>
            </Table>
         </Card>
      </div>
   );
};

TrainerMetricsIndex.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default TrainerMetricsIndex;
