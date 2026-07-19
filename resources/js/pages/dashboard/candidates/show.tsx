import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { Link } from '@inertiajs/react';
import { ChevronDown, ChevronLeft } from 'lucide-react';
import { ReactNode, useState } from 'react';
import { CandidateStatus } from './partials/table-columns';
import ProcessRefundSection from './partials/process-refund-section';
import StatusUpdateForm from './partials/status-update-form';

interface StatusOption {
   value: string;
   label: string;
}

interface CandidateCourseProgress {
   enrollment_id: number;
   course_id: number;
   course_title: string;
   enrolled_at: string;
   completion: CourseCompletion;
   course_gates: CourseGates;
   quizzes_passed: number;
   quizzes_total: number;
   assignments_submitted: number;
   assignments_total: number;
   quizzes: { title: string; attempted: boolean; score: number | null; is_passed: boolean | null }[];
   assignments: { title: string; submitted: boolean; status: string; marks_obtained: number | null }[];
}

interface CandidateExamProgress {
   enrollment_id: number;
   exam_id: number;
   exam_title: string;
   enrolled_at: string;
   pass_mark: number;
   total_marks: number;
   best_marks: number;
   best_percentage: number;
   grade: string;
   is_passed: boolean;
   attempt_count: number;
}

interface RefundAttempt {
   id: number;
   gateway: string;
   transaction_id: string | null;
   success: boolean;
   gateway_refund_id: string | null;
   error_message: string | null;
   created_at: string;
   initiated_by?: { id: number; name: string; email: string };
   payment_history?: PaymentHistory;
}

interface Props extends SharedData {
   candidate: User;
   paid_courses: CandidateCourseProgress[];
   paid_exams: CandidateExamProgress[];
   refundable_payments: PaymentHistory[];
   refund_attempts: RefundAttempt[];
   statuses: StatusOption[];
}

const statusVariant: Record<CandidateStatus, 'default' | 'secondary' | 'outline' | 'destructive'> = {
   new: 'secondary',
   in_review: 'outline',
   shortlisted: 'default',
   hired: 'default',
   rejected: 'destructive',
};

const statusLabel: Record<CandidateStatus, string> = {
   new: 'New',
   in_review: 'In Review',
   shortlisted: 'Shortlisted',
   hired: 'Hired',
   rejected: 'Rejected',
};

const PassBadge = ({ passed }: { passed: boolean | null | undefined }) => {
   if (passed === null || passed === undefined) {
      return <Badge variant="secondary">—</Badge>;
   }

   return <Badge variant={passed ? 'default' : 'destructive'}>{passed ? 'Passed' : 'Failed'}</Badge>;
};

const CourseRow = ({ course, dashboard }: { course: CandidateCourseProgress; dashboard: Record<string, string> }) => {
   const [expanded, setExpanded] = useState(false);

   return (
      <>
         <TableRow>
            <TableCell className="font-medium">{course.course_title}</TableCell>
            <TableCell className="text-sm">{new Date(course.enrolled_at).toLocaleDateString()}</TableCell>
            <TableCell>
               <div className="space-y-1">
                  <div className="flex justify-between text-sm">
                     <span>{course.completion.completion}%</span>
                     <span className="text-muted-foreground text-xs">
                        {course.completion.completed_items}/{course.completion.total_items}
                     </span>
                  </div>
                  <Progress value={course.completion.completion} className="h-2" />
               </div>
            </TableCell>
            <TableCell className="text-sm">
               {course.quizzes_passed}/{course.quizzes_total} {dashboard.passed ?? 'passed'}
            </TableCell>
            <TableCell className="text-sm">
               {course.assignments_submitted}/{course.assignments_total} {dashboard.submitted ?? 'submitted'}
            </TableCell>
            <TableCell>
               <Badge variant={course.course_gates.certificate_unlocked ? 'default' : 'secondary'}>
                  {course.course_gates.certificate_unlocked ? dashboard.complete ?? 'Complete' : dashboard.in_progress ?? 'In Progress'}
               </Badge>
            </TableCell>
            <TableCell>
               <Button variant="ghost" size="sm" onClick={() => setExpanded(!expanded)}>
                  {expanded ? <ChevronDown className="h-4 w-4" /> : <ChevronDown className="h-4 w-4 -rotate-90" />}
               </Button>
            </TableCell>
         </TableRow>
         {expanded && (
            <TableRow>
               <TableCell colSpan={7} className="bg-muted/30">
                  <div className="grid gap-4 p-4 md:grid-cols-2">
                     <div>
                        <p className="mb-2 text-sm font-medium">{dashboard.quizzes ?? 'Quizzes'}</p>
                        {course.quizzes.length === 0 ? (
                           <p className="text-muted-foreground text-sm">—</p>
                        ) : (
                           <ul className="space-y-1 text-sm">
                              {course.quizzes.map((quiz, index) => (
                                 <li key={index} className="flex items-center justify-between gap-2">
                                    <span>{quiz.title}</span>
                                    <PassBadge passed={quiz.is_passed} />
                                 </li>
                              ))}
                           </ul>
                        )}
                     </div>
                     <div>
                        <p className="mb-2 text-sm font-medium">{dashboard.assignments ?? 'Assignments'}</p>
                        {course.assignments.length === 0 ? (
                           <p className="text-muted-foreground text-sm">—</p>
                        ) : (
                           <ul className="space-y-1 text-sm">
                              {course.assignments.map((assignment, index) => (
                                 <li key={index} className="flex items-center justify-between gap-2">
                                    <span>{assignment.title}</span>
                                    <Badge variant="outline">{assignment.status}</Badge>
                                 </li>
                              ))}
                           </ul>
                        )}
                     </div>
                  </div>
               </TableCell>
            </TableRow>
         )}
      </>
   );
};

const CandidateShow = (props: Props) => {
   const { candidate, paid_courses, paid_exams, refundable_payments, refund_attempts, statuses, translate } = props;
   const { dashboard } = translate;
   const status = (candidate.candidate_status ?? 'new') as CandidateStatus;
   const professionalLabel = candidate.professional_type?.name ?? candidate.professional_type_other ?? '—';

   return (
      <div className="space-y-6">
         <div className="flex items-center gap-3">
            <Button variant="outline" size="sm" asChild>
               <Link href={route('candidates.index')}>
                  <ChevronLeft className="mr-1 h-4 w-4" />
                  {dashboard.back ?? 'Back'}
               </Link>
            </Button>
            <div>
               <h1 className="text-xl font-semibold">{candidate.name}</h1>
               <p className="text-muted-foreground text-sm">{candidate.email}</p>
            </div>
            <Badge variant={statusVariant[status]} className="ml-auto">
               {statusLabel[status]}
            </Badge>
         </div>

         <div className="grid gap-6 lg:grid-cols-3">
            <Card className="lg:col-span-2">
               <CardHeader>
                  <CardTitle>{dashboard.candidate_profile ?? 'Profile'}</CardTitle>
               </CardHeader>
               <CardContent className="grid gap-4 sm:grid-cols-2">
                  <div>
                     <p className="text-muted-foreground text-sm">{dashboard.professional_type ?? 'Professional Type'}</p>
                     <p className="font-medium">{professionalLabel}</p>
                  </div>
                  <div>
                     <p className="text-muted-foreground text-sm">{dashboard.registered ?? 'Registered'}</p>
                     <p className="font-medium">{new Date(candidate.created_at).toLocaleDateString()}</p>
                  </div>
                  <div>
                     <p className="text-muted-foreground text-sm">{dashboard.cv_resume ?? 'CV / Resume'}</p>
                     {candidate.has_cv ? (
                        <div className="flex gap-3">
                           <a href={route('users.cv.view', { id: candidate.id })} target="_blank" rel="noreferrer" className="text-primary text-sm hover:underline">
                              {dashboard.view_cv ?? 'View CV'}
                           </a>
                           <a href={route('users.cv.download', { id: candidate.id })} className="text-primary text-sm hover:underline">
                              {dashboard.download_cv ?? 'Download'}
                           </a>
                        </div>
                     ) : (
                        <p className="text-sm">—</p>
                     )}
                  </div>
                  <div>
                     <p className="text-muted-foreground text-sm">{dashboard.paid_enrollments ?? 'Paid Enrollments'}</p>
                     <p className="font-medium">
                        {paid_courses.length} {dashboard.courses ?? 'courses'}, {paid_exams.length} {dashboard.exams ?? 'exams'}
                     </p>
                  </div>
                  <div>
                     <p className="text-muted-foreground text-sm">{dashboard.payment_history ?? 'Payment History'}</p>
                     <Link href={route('payment-refunds.user', { user: candidate.id })} className="text-primary text-sm hover:underline">
                        {dashboard.view_payment_history ?? 'View Payment History'}
                     </Link>
                  </div>
               </CardContent>
            </Card>

            <Card>
               <CardHeader>
                  <CardTitle>{dashboard.pipeline_status ?? 'Pipeline Status'}</CardTitle>
               </CardHeader>
               <CardContent>
                  <StatusUpdateForm candidate={candidate} statuses={statuses} />
                  {candidate.candidate_status_updated_at && (
                     <p className="text-muted-foreground mt-4 text-xs">
                        {dashboard.last_updated ?? 'Last updated'}: {new Date(candidate.candidate_status_updated_at).toLocaleString()}
                     </p>
                  )}
               </CardContent>
            </Card>
         </div>

         <ProcessRefundSection
            candidate={candidate}
            refundablePayments={refundable_payments ?? []}
            refundAttempts={refund_attempts ?? []}
         />

         <Card>
            <CardHeader>
               <CardTitle>{dashboard.paid_courses ?? 'Paid Courses'}</CardTitle>
            </CardHeader>
            <CardContent>
               {paid_courses.length === 0 ? (
                  <p className="text-muted-foreground text-sm">{dashboard.no_paid_courses ?? 'No paid course enrollments yet.'}</p>
               ) : (
                  <Table>
                     <TableHeader>
                        <TableRow>
                           <TableHead>{dashboard.course ?? 'Course'}</TableHead>
                           <TableHead>{dashboard.enrolled ?? 'Enrolled'}</TableHead>
                           <TableHead>{dashboard.completion ?? 'Completion'}</TableHead>
                           <TableHead>{dashboard.quizzes ?? 'Quizzes'}</TableHead>
                           <TableHead>{dashboard.assignments ?? 'Assignments'}</TableHead>
                           <TableHead>{dashboard.certificate ?? 'Certificate'}</TableHead>
                           <TableHead />
                        </TableRow>
                     </TableHeader>
                     <TableBody>
                        {paid_courses.map((course) => (
                           <CourseRow key={course.enrollment_id} course={course} dashboard={dashboard} />
                        ))}
                     </TableBody>
                  </Table>
               )}
            </CardContent>
         </Card>

         <Card>
            <CardHeader>
               <CardTitle>{dashboard.paid_exams ?? 'Paid Exams'}</CardTitle>
            </CardHeader>
            <CardContent>
               {paid_exams.length === 0 ? (
                  <p className="text-muted-foreground text-sm">{dashboard.no_paid_exams ?? 'No paid exam enrollments yet.'}</p>
               ) : (
                  <Table>
                     <TableHeader>
                        <TableRow>
                           <TableHead>{dashboard.exam ?? 'Exam'}</TableHead>
                           <TableHead>{dashboard.enrolled ?? 'Enrolled'}</TableHead>
                           <TableHead>{dashboard.best_score ?? 'Best Score'}</TableHead>
                           <TableHead>{dashboard.grade ?? 'Grade'}</TableHead>
                           <TableHead>{dashboard.attempts ?? 'Attempts'}</TableHead>
                           <TableHead>{dashboard.result ?? 'Result'}</TableHead>
                        </TableRow>
                     </TableHeader>
                     <TableBody>
                        {paid_exams.map((exam) => (
                           <TableRow key={exam.enrollment_id}>
                              <TableCell className="font-medium">{exam.exam_title}</TableCell>
                              <TableCell>{new Date(exam.enrolled_at).toLocaleDateString()}</TableCell>
                              <TableCell>
                                 {exam.best_marks}/{exam.total_marks} ({exam.best_percentage}%)
                              </TableCell>
                              <TableCell>{exam.grade}</TableCell>
                              <TableCell>{exam.attempt_count}</TableCell>
                              <TableCell>
                                 <PassBadge passed={exam.is_passed} />
                              </TableCell>
                           </TableRow>
                        ))}
                     </TableBody>
                  </Table>
               )}
            </CardContent>
         </Card>

         {candidate.candidate_notes && (
            <Card>
               <CardHeader>
                  <CardTitle>{dashboard.candidate_notes ?? 'Admin Notes'}</CardTitle>
               </CardHeader>
               <CardContent>
                  <p className="text-sm whitespace-pre-wrap">{candidate.candidate_notes}</p>
               </CardContent>
            </Card>
         )}
      </div>
   );
};

CandidateShow.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default CandidateShow;
