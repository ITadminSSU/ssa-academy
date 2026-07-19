import TableFilter from '@/components/table/table-filter';
import TableFooter from '@/components/table/table-footer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { getQueryParams } from '@/lib/route';
import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronLeft } from 'lucide-react';
import { ReactNode, useState } from 'react';

interface Props extends SharedData {
   course: Course;
   students: CourseStudentProgressRow[];
   enrollments: Pagination<CourseEnrollment>;
   summary: {
      total_enrollments: number;
      quiz_count: number;
      assignment_count: number;
   };
   sort_by: StudentProgressSortBy;
}

const formatPercent = (value: number | null | undefined, fallback: string) => {
   if (value === null || value === undefined) {
      return fallback;
   }

   return `${value}%`;
};

const StatusBadge = ({ passed, label }: { passed: boolean | null | undefined; label?: string }) => {
   if (passed === null || passed === undefined) {
      return <Badge variant="secondary">{label ?? '—'}</Badge>;
   }

   return <Badge variant={passed ? 'default' : 'destructive'}>{passed ? 'Passed' : 'Failed'}</Badge>;
};

const AssignmentStatusBadge = ({ status }: { status: string }) => {
   const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
      graded: 'default',
      pending: 'secondary',
      late: 'destructive',
      resubmitted: 'outline',
      not_submitted: 'destructive',
   };

   const labels: Record<string, string> = {
      graded: 'Graded',
      pending: 'Pending',
      late: 'Late',
      resubmitted: 'Resubmitted',
      not_submitted: 'Not submitted',
   };

   return <Badge variant={variants[status] ?? 'secondary'}>{labels[status] ?? status}</Badge>;
};

const StudentRow = ({
   row,
   translate,
   expanded,
   onToggle,
}: {
   row: CourseStudentProgressRow;
   translate: LanguageTranslations;
   expanded: boolean;
   onToggle: () => void;
}) => {
   const { dashboard } = translate;
   const user = row.enrollment.user;
   const passedQuizzes = row.quizzes.filter((q) => q.is_passed).length;
   const submittedAssignments = row.assignments.filter((a) => a.submitted).length;

   return (
      <>
         <TableRow>
            <TableCell>
               <div className="flex items-center gap-3">
                  <div className="bg-muted flex h-10 w-10 items-center justify-center overflow-hidden rounded-full">
                     {user.photo ? (
                        <img src={user.photo} alt={user.name} className="h-full w-full object-cover" />
                     ) : (
                        <span className="text-sm font-medium">{user.name.charAt(0)}</span>
                     )}
                  </div>
                  <div>
                     <p className="font-medium">{user.name}</p>
                     <p className="text-muted-foreground text-xs">{user.email}</p>
                  </div>
               </div>
            </TableCell>
            <TableCell>
               <span className="text-sm font-medium">
                  {formatPercent(row.overall_score_percent, dashboard.no_score_yet)}
               </span>
            </TableCell>
            <TableCell>
               <span className="text-sm">{formatPercent(row.best_quiz_percent, dashboard.no_score_yet)}</span>
            </TableCell>
            <TableCell>
               <div className="space-y-1">
                  <div className="flex items-center justify-between text-sm">
                     <span>{row.completion.completion}%</span>
                     <span className="text-muted-foreground text-xs">
                        {row.completion.completed_items}/{row.completion.total_items}
                     </span>
                  </div>
                  <Progress value={row.completion.completion} className="h-2" />
               </div>
            </TableCell>
            <TableCell>
               <span className="text-sm">
                  {submittedAssignments}/{row.assignments.length} {dashboard.submitted}
               </span>
            </TableCell>
            <TableCell>
               <span className="text-sm">
                  {passedQuizzes}/{row.quizzes.length} {dashboard.passed_label}
               </span>
            </TableCell>
            <TableCell>
               {row.exams.length > 0 ? (
                  <span className="text-sm">
                     {row.exams.filter((e) => e.is_passed).length}/{row.exams.length} {dashboard.passed_label}
                  </span>
               ) : (
                  <span className="text-muted-foreground text-sm">—</span>
               )}
            </TableCell>
            <TableCell>
               <Badge variant={row.course_gates.certificate_unlocked ? 'default' : 'secondary'}>
                  {row.course_gates.certificate_unlocked ? dashboard.certificate_ready : dashboard.in_progress}
               </Badge>
            </TableCell>
            <TableCell className="text-end">
               <Button size="sm" variant="ghost" onClick={onToggle}>
                  {dashboard.details}
                  <ChevronDown className={`ml-1 h-4 w-4 transition-transform ${expanded ? 'rotate-180' : ''}`} />
               </Button>
            </TableCell>
         </TableRow>
         {expanded && (
            <TableRow className="bg-muted/30 hover:bg-muted/30">
               <TableCell colSpan={9} className="p-4">
                  <div className="grid gap-4 md:grid-cols-3">
                     {row.assignments.length > 0 && (
                        <div>
                           <p className="mb-2 text-sm font-semibold">{dashboard.assignments}</p>
                           <ul className="space-y-2">
                              {row.assignments.map((assignment) => (
                                 <li key={assignment.assignment_id} className="rounded-md border bg-background p-2 text-sm">
                                    <p className="font-medium">{assignment.title}</p>
                                    <div className="mt-1 flex flex-wrap items-center gap-2">
                                       <AssignmentStatusBadge status={assignment.status} />
                                       {assignment.marks_obtained !== null && (
                                          <span>
                                             {assignment.marks_obtained}/{assignment.total_mark}
                                          </span>
                                       )}
                                       <StatusBadge passed={assignment.is_passed} />
                                    </div>
                                 </li>
                              ))}
                           </ul>
                        </div>
                     )}

                     {row.quizzes.length > 0 && (
                        <div>
                           <p className="mb-2 text-sm font-semibold">{dashboard.quizzes}</p>
                           <ul className="space-y-2">
                              {row.quizzes.map((quiz) => (
                                 <li key={quiz.quiz_id} className="rounded-md border bg-background p-2 text-sm">
                                    <p className="font-medium">{quiz.title}</p>
                                    <div className="mt-1 flex flex-wrap items-center gap-2">
                                       {quiz.attempted ? (
                                          <>
                                             <span>
                                                {quiz.score}/{quiz.total_mark}
                                             </span>
                                             <StatusBadge passed={quiz.is_passed} />
                                             <span className="text-muted-foreground text-xs">
                                                {quiz.attempts} {dashboard.attempts}
                                             </span>
                                          </>
                                       ) : (
                                          <Badge variant="destructive">Not attempted</Badge>
                                       )}
                                    </div>
                                 </li>
                              ))}
                           </ul>
                        </div>
                     )}

                     <div>
                        <p className="mb-2 text-sm font-semibold">{dashboard.standalone_exams}</p>
                        {row.exams.length > 0 ? (
                           <ul className="space-y-2">
                              {row.exams.map((exam) => (
                                 <li key={exam.exam_id} className="rounded-md border bg-background p-2 text-sm">
                                    <p className="font-medium">{exam.title}</p>
                                    <div className="mt-1 flex flex-wrap items-center gap-2">
                                       <span>
                                          {exam.score}/{exam.total_marks}
                                       </span>
                                       <StatusBadge passed={exam.is_passed} />
                                    </div>
                                 </li>
                              ))}
                           </ul>
                        ) : (
                           <p className="text-muted-foreground text-sm">{dashboard.no_standalone_exams}</p>
                        )}
                     </div>
                  </div>
               </TableCell>
            </TableRow>
         )}
      </>
   );
};

const Show = ({ course, students, enrollments, summary, sort_by, translate }: Props) => {
   const { dashboard, button } = translate;
   const [expandedId, setExpandedId] = useState<number | null>(null);
   const page = usePage<SharedData>();
   const { auth } = page.props;
   const isAdmin = auth.user.role === 'admin';
   const progressIndexRoute = isAdmin ? 'admin.student-progress.index' : 'student-progress.index';
   const progressShowRoute = isAdmin ? 'admin.student-progress.show' : 'student-progress.show';
   const urlParams = getQueryParams(page.url);

   const handleSortChange = (value: StudentProgressSortBy) => {
      router.get(
         route(progressShowRoute, { course: course.id, ...urlParams, sort_by: value }),
         {},
         { preserveState: true, preserveScroll: true },
      );
   };

   return (
      <div className="space-y-6">
         <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
               <Button asChild variant="ghost" size="sm" className="mb-2 -ml-2">
                  <Link href={route(progressIndexRoute)}>
                     <ChevronLeft className="mr-1 h-4 w-4" />
                     {button.back}
                  </Link>
               </Button>
               <h1 className="text-2xl font-bold">{course.title}</h1>
               <p className="text-muted-foreground">
                  {dashboard.grading_roster_description ?? dashboard.student_progress_course_description}
               </p>
            </div>
         </div>

         <div className="grid gap-4 sm:grid-cols-3">
            <Card>
               <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium">{dashboard.enrolled_students}</CardTitle>
               </CardHeader>
               <CardContent className="text-2xl font-bold">{summary.total_enrollments}</CardContent>
            </Card>
            <Card>
               <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium">{dashboard.assignments}</CardTitle>
               </CardHeader>
               <CardContent className="text-2xl font-bold">{summary.assignment_count}</CardContent>
            </Card>
            <Card>
               <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium">{dashboard.quizzes}</CardTitle>
               </CardHeader>
               <CardContent className="text-2xl font-bold">{summary.quiz_count}</CardContent>
            </Card>
         </div>

         <Card>
            <div className="flex flex-wrap items-center justify-between gap-4 border-b p-5 sm:p-7">
               <TableFilter
                  data={enrollments}
                  title={dashboard.enrollment_list}
                  globalSearch={true}
                  tablePageSizes={[10, 15, 20, 25]}
                  routeName={progressShowRoute}
                  routeParams={{ course: course.id }}
                  className="border-0 p-0"
               />

               <div className="flex items-center gap-2">
                  <span className="text-muted-foreground text-sm">{dashboard.sort_students_by}</span>
                  <Select value={sort_by} onValueChange={handleSortChange}>
                     <SelectTrigger className="w-[240px]">
                        <SelectValue />
                     </SelectTrigger>
                     <SelectContent>
                        <SelectItem value="name">{dashboard.sort_by_name}</SelectItem>
                        <SelectItem value="overall_score">{dashboard.sort_by_overall_score}</SelectItem>
                        <SelectItem value="best_quiz">{dashboard.sort_by_best_quiz}</SelectItem>
                        <SelectItem value="completion">{dashboard.sort_by_completion}</SelectItem>
                     </SelectContent>
                  </Select>
               </div>
            </div>

            <Table>
               <TableHeader>
                  <TableRow>
                     <TableHead>{dashboard.student}</TableHead>
                     <TableHead>{dashboard.course_score}</TableHead>
                     <TableHead>{dashboard.best_quiz_score}</TableHead>
                     <TableHead>{dashboard.completion}</TableHead>
                     <TableHead>{dashboard.assignments}</TableHead>
                     <TableHead>{dashboard.quizzes}</TableHead>
                     <TableHead>{dashboard.standalone_exams}</TableHead>
                     <TableHead>{dashboard.certificate}</TableHead>
                     <TableHead className="text-end">{dashboard.details}</TableHead>
                  </TableRow>
               </TableHeader>
               <TableBody>
                  {students.length > 0 ? (
                     students.map((row) => (
                        <StudentRow
                           key={row.enrollment.id}
                           row={row}
                           translate={translate}
                           expanded={expandedId === row.enrollment.id}
                           onToggle={() => setExpandedId((current) => (current === row.enrollment.id ? null : row.enrollment.id))}
                        />
                     ))
                  ) : (
                     <TableRow>
                        <TableCell colSpan={9} className="h-24 text-center">
                           {dashboard.no_results}
                        </TableCell>
                     </TableRow>
                  )}
               </TableBody>
            </Table>

            <TableFooter
               className="p-5 sm:p-7"
               routeName={progressShowRoute}
               routeParams={{ course: course.id }}
               paginationInfo={enrollments}
            />
         </Card>
      </div>
   );
};

Show.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default Show;
