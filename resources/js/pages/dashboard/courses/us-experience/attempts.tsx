import TableFooter from '@/components/table/table-footer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
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
   plan?: { id: number; title: string; group_name?: string | null } | null;
}

interface PlanOption {
   id: number;
   title: string;
   group_name?: string | null;
}

interface Props extends SharedData {
   course: { id: number; title: string };
   plan: UsExperiencePlan | null;
   plans?: PlanOption[];
   attempts: Pagination<TrainerAttemptRow>;
   filters: { search: string; plan_id?: string };
}

const formatWhen = (value?: string | null) => {
   if (!value) {
      return '—';
   }

   const date = new Date(value);

   return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
};

const Attempts = ({ course, plan, plans = [], attempts, filters }: Props) => {
   const [search, setSearch] = useState(filters.search ?? '');
   const isCourseWide = !plan;
   const listRoute = isCourseWide ? 'courses.us-experience.attempts.course' : 'courses.us-experience.attempts.index';
   const listParams = isCourseWide ? { course: course.id } : { course: course.id, plan: plan.id };

   const applyFilters = (next: { search?: string; plan_id?: string }) => {
      router.get(
         route(listRoute, listParams),
         {
            search: next.search ?? search,
            ...(isCourseWide ? { plan_id: next.plan_id ?? filters.plan_id ?? '' } : {}),
         },
         { preserveState: true, preserveScroll: true },
      );
   };

   const submitSearch = (event: FormEvent) => {
      event.preventDefault();
      applyFilters({ search });
   };

   return (
      <div className="space-y-6">
         <div>
            {isCourseWide ? (
               <>
                  <p className="text-muted-foreground text-sm">{course.title}</p>
                  <div className="mt-1 flex flex-wrap items-center justify-between gap-3">
                     <h1 className="text-2xl font-bold">US Experience attempts</h1>
                     <Button variant="outline" asChild>
                        <Link href={route('courses.edit', { course: course.id, tab: 'us-experience' })}>Plan setup</Link>
                     </Button>
                  </div>
                  <p className="text-muted-foreground mt-2 text-sm">
                     All student submissions across this course. Open an attempt to download files, see scores, and leave feedback.
                  </p>
               </>
            ) : (
               <>
                  <p className="text-muted-foreground text-sm">{course.title}</p>
                  <UsExperiencePlanWorkspaceHeader courseId={course.id} plan={plan} current="attempts" />
                  <p className="text-muted-foreground mt-2 text-sm">
                     Every student submission for this plan. Open an attempt to download the takeoff PDF and Excel BOQ, see scores, and leave
                     feedback.
                  </p>
               </>
            )}
         </div>

         <Card className="space-y-4 overflow-hidden p-4 sm:p-6">
            <form onSubmit={submitSearch} className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
               <Input
                  className="max-w-md"
                  value={search}
                  onChange={(event) => setSearch(event.target.value)}
                  placeholder="Search student name or email"
               />
               {isCourseWide && plans.length > 0 && (
                  <Select
                     value={filters.plan_id || 'all'}
                     onValueChange={(value) => applyFilters({ plan_id: value === 'all' ? '' : value })}
                  >
                     <SelectTrigger className="w-[240px]">
                        <SelectValue placeholder="All plans" />
                     </SelectTrigger>
                     <SelectContent>
                        <SelectItem value="all">All plans</SelectItem>
                        {plans.map((option) => (
                           <SelectItem key={option.id} value={String(option.id)}>
                              {option.title}
                           </SelectItem>
                        ))}
                     </SelectContent>
                  </Select>
               )}
               <Button type="submit" variant="outline">
                  Search
               </Button>
            </form>

            <Table className="border-border min-w-3xl border-y">
               <TableHeader>
                  <TableRow>
                     <TableHead>Student</TableHead>
                     {isCourseWide ? <TableHead>Plan</TableHead> : null}
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
                     attempts.data.map((attempt) => {
                        const planId = plan?.id ?? attempt.plan?.id;

                        return (
                           <TableRow key={attempt.id}>
                              <TableCell>
                                 <p className="font-medium">{attempt.user?.name ?? 'Unknown'}</p>
                                 <p className="text-muted-foreground text-xs">{attempt.user?.email}</p>
                              </TableCell>
                              {isCourseWide ? (
                                 <TableCell>
                                    <p className="font-medium">{attempt.plan?.title ?? '—'}</p>
                                    {attempt.plan?.group_name ? (
                                       <p className="text-muted-foreground text-xs">{attempt.plan.group_name}</p>
                                    ) : null}
                                 </TableCell>
                              ) : null}
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
                                 {planId ? (
                                    <Button size="sm" variant="outline" asChild>
                                       <Link
                                          href={route('courses.us-experience.attempts.show', {
                                             course: course.id,
                                             plan: planId,
                                             attempt: attempt.id,
                                          })}
                                       >
                                          Open
                                       </Link>
                                    </Button>
                                 ) : null}
                              </TableCell>
                           </TableRow>
                        );
                     })
                  ) : (
                     <TableRow>
                        <TableCell colSpan={isCourseWide ? 8 : 7} className="text-muted-foreground h-24 text-center">
                           {isCourseWide ? 'No student attempts yet for this course.' : 'No student attempts yet for this plan.'}
                        </TableCell>
                     </TableRow>
                  )}
               </TableBody>
            </Table>

            <TableFooter className="p-0" routeName={listRoute} routeParams={listParams} paginationInfo={attempts} />
         </Card>
      </div>
   );
};

Attempts.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default Attempts;
