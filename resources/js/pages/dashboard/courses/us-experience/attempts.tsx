import TableFooter from '@/components/table/table-footer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { Link, router } from '@inertiajs/react';
import { FormEvent, ReactNode, useState } from 'react';
import UsExperiencePlanWorkspaceHeader from '../partials/us-experience-plan-workspace-header';

interface TrainerAttemptRow {
   id: number;
   attempt_number: number;
   status: 'passed' | 'failed';
   lines_correct?: number | null;
   lines_total?: number | null;
   lines_percent?: number | null;
   submitted_at?: string | null;
   takeoff_pdf_name?: string | null;
   boq_xlsx_name?: string | null;
   has_pdf: boolean;
   has_excel: boolean;
   trainer_feedback?: string | null;
   user?: { id: number; name: string; email: string } | null;
}

interface Props extends SharedData {
   course: { id: number; title: string };
   plan: UsExperiencePlan;
   attempts: Pagination<TrainerAttemptRow>;
   filters: { search: string };
}

const formatWhen = (value?: string | null) => {
   if (!value) {
      return '—';
   }

   const date = new Date(value);

   return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
};

const Attempts = ({ course, plan, attempts, filters }: Props) => {
   const [search, setSearch] = useState(filters.search ?? '');

   const submitSearch = (event: FormEvent) => {
      event.preventDefault();
      router.get(
         route('courses.us-experience.attempts.index', { course: course.id, plan: plan.id }),
         { search },
         { preserveState: true, preserveScroll: true },
      );
   };

   return (
      <div className="space-y-6">
         <div>
            <p className="text-muted-foreground text-sm">{course.title}</p>
            <UsExperiencePlanWorkspaceHeader courseId={course.id} plan={plan} current="attempts" />
            <p className="text-muted-foreground mt-2 text-sm">
               Every student submission for this plan. Open an attempt to download the takeoff PDF and Excel BOQ, see scores, and leave
               feedback.
            </p>
         </div>

         <Card className="space-y-4 overflow-hidden p-4 sm:p-6">
            <form onSubmit={submitSearch} className="flex max-w-md gap-2">
               <Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search student name or email" />
               <Button type="submit" variant="outline">
                  Search
               </Button>
            </form>

            <Table className="border-border min-w-3xl border-y">
               <TableHeader>
                  <TableRow>
                     <TableHead>Student</TableHead>
                     <TableHead>Attempt</TableHead>
                     <TableHead>Submitted</TableHead>
                     <TableHead>Accuracy</TableHead>
                     <TableHead>Result</TableHead>
                     <TableHead>Files</TableHead>
                     <TableHead className="text-right">Review</TableHead>
                  </TableRow>
               </TableHeader>
               <TableBody>
                  {attempts.data.length ? (
                     attempts.data.map((attempt) => (
                        <TableRow key={attempt.id}>
                           <TableCell>
                              <p className="font-medium">{attempt.user?.name ?? 'Unknown'}</p>
                              <p className="text-muted-foreground text-xs">{attempt.user?.email}</p>
                           </TableCell>
                           <TableCell>#{attempt.attempt_number}</TableCell>
                           <TableCell className="text-sm">{formatWhen(attempt.submitted_at)}</TableCell>
                           <TableCell>
                              {attempt.lines_correct ?? 0}/{attempt.lines_total ?? 0}
                              {attempt.lines_percent != null ? ` · ${attempt.lines_percent}%` : ''}
                           </TableCell>
                           <TableCell>
                              <Badge variant={attempt.status === 'passed' ? 'default' : 'destructive'}>
                                 {attempt.status === 'passed' ? 'Passed' : 'Failed'}
                              </Badge>
                           </TableCell>
                           <TableCell className="text-muted-foreground text-xs">
                              {attempt.has_pdf ? 'PDF' : 'No PDF'}
                              {' · '}
                              {attempt.has_excel ? 'Excel' : 'No Excel'}
                              {attempt.trainer_feedback ? ' · Feedback' : ''}
                           </TableCell>
                           <TableCell className="text-right">
                              <Button size="sm" variant="outline" asChild>
                                 <Link
                                    href={route('courses.us-experience.attempts.show', {
                                       course: course.id,
                                       plan: plan.id,
                                       attempt: attempt.id,
                                    })}
                                 >
                                    Open
                                 </Link>
                              </Button>
                           </TableCell>
                        </TableRow>
                     ))
                  ) : (
                     <TableRow>
                        <TableCell colSpan={7} className="text-muted-foreground h-24 text-center">
                           No student attempts yet for this plan.
                        </TableCell>
                     </TableRow>
                  )}
               </TableBody>
            </Table>

            <TableFooter
               className="p-0"
               routeName="courses.us-experience.attempts.index"
               routeParams={{ course: course.id, plan: plan.id }}
               paginationInfo={attempts}
            />
         </Card>
      </div>
   );
};

Attempts.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default Attempts;
