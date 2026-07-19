import TableFilter from '@/components/table/table-filter';
import TableFooter from '@/components/table/table-footer';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import DashboardLayout from '@/layouts/dashboard/layout';
import { getQueryParams } from '@/lib/route';
import { SharedData } from '@/types/global';
import { Link, router, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

interface LeaderboardAttempt {
   id: number;
   tracking_reference: string | null;
   obtained_marks: number;
   total_marks: number;
   is_passed: boolean;
   status: string;
   end_time: string | null;
   user: { id: number; name: string; email: string };
   exam: { id: number; title: string; total_marks: number; pass_mark: number };
}

interface Props extends SharedData {
   attempts: Pagination<LeaderboardAttempt>;
   exams: { id: number; title: string }[];
   filters: { search: string; exam_id: string };
}

const Leaderboard = ({ attempts, exams, filters, translate }: Props) => {
   const { dashboard } = translate;
   const page = usePage<SharedData>();
   const urlParams = getQueryParams(page.url);

   const handleExamFilter = (value: string) => {
      router.get(
         route('exams.leaderboard', {
            ...urlParams,
            exam_id: value === 'all' ? undefined : value,
         }),
         {},
         { preserveState: true, preserveScroll: true },
      );
   };

   return (
      <div className="space-y-6">
         <div>
            <h1 className="text-2xl font-bold">{dashboard.exam_leaderboard_title ?? 'Exam Talent Leaderboard'}</h1>
            <p className="text-muted-foreground">
               {dashboard.exam_leaderboard_description ??
                  'All test-takers ranked by score to highlight high-potential talent. Each attempt has a unique tracking reference.'}
            </p>
         </div>

         <Card>
            <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-4">
               <CardTitle className="text-base">{dashboard.all_exam_attempts ?? 'All Exam Attempts'}</CardTitle>
               <Select value={filters.exam_id || 'all'} onValueChange={handleExamFilter}>
                  <SelectTrigger className="w-[260px]">
                     <SelectValue placeholder="Filter by exam" />
                  </SelectTrigger>
                  <SelectContent>
                     <SelectItem value="all">{dashboard.all_exams ?? 'All exams'}</SelectItem>
                     {exams.map((exam) => (
                        <SelectItem key={exam.id} value={String(exam.id)}>
                           {exam.title}
                        </SelectItem>
                     ))}
                  </SelectContent>
               </Select>
            </CardHeader>

            <TableFilter
               data={attempts}
               title=""
               globalSearch
               tablePageSizes={[20, 50, 100]}
               routeName="exams.leaderboard"
               className="border-b px-5 sm:px-7"
            />

            <CardContent className="p-0">
               <Table>
                  <TableHeader>
                     <TableRow>
                        <TableHead className="w-16">#</TableHead>
                        <TableHead>{dashboard.student ?? 'Student'}</TableHead>
                        <TableHead>{dashboard.exam ?? 'Exam'}</TableHead>
                        <TableHead>{dashboard.tracking_reference ?? 'Tracking Reference'}</TableHead>
                        <TableHead>{dashboard.score ?? 'Score'}</TableHead>
                        <TableHead>{dashboard.result ?? 'Result'}</TableHead>
                     </TableRow>
                  </TableHeader>
                  <TableBody>
                     {attempts.data.length > 0 ? (
                        attempts.data.map((attempt, index) => (
                           <TableRow key={attempt.id}>
                              <TableCell className="font-medium">{(attempts.from ?? 1) + index}</TableCell>
                              <TableCell>
                                 <p className="font-medium">{attempt.user.name}</p>
                                 <p className="text-muted-foreground text-xs">{attempt.user.email}</p>
                              </TableCell>
                              <TableCell>
                                 <Link href={route('exams.edit', attempt.exam.id)} className="hover:underline">
                                    {attempt.exam.title}
                                 </Link>
                              </TableCell>
                              <TableCell>
                                 <code className="text-xs">{attempt.tracking_reference ?? '—'}</code>
                              </TableCell>
                              <TableCell>
                                 {attempt.obtained_marks}/{attempt.total_marks}
                              </TableCell>
                              <TableCell>
                                 <Badge variant={attempt.is_passed ? 'default' : 'destructive'}>
                                    {attempt.is_passed ? dashboard.passed_label ?? 'Passed' : dashboard.failed_label ?? 'Failed'}
                                 </Badge>
                              </TableCell>
                           </TableRow>
                        ))
                     ) : (
                        <TableRow>
                           <TableCell colSpan={6} className="h-24 text-center">
                              {dashboard.no_results ?? 'No results'}
                           </TableCell>
                        </TableRow>
                     )}
                  </TableBody>
               </Table>
            </CardContent>

            <TableFooter className="p-5 sm:p-7" routeName="exams.leaderboard" paginationInfo={attempts} />
         </Card>
      </div>
   );
};

Leaderboard.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default Leaderboard;
