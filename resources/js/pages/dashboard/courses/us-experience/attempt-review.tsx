import QuantityTakeoffBreakdown, { QuantityTakeoffBreakdownLine } from '@/components/exam/quantity-takeoff-breakdown';
import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { Link, useForm } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';
import UsExperiencePlanWorkspaceHeader from '../partials/us-experience-plan-workspace-header';

interface TrainerAttemptDetail {
   id: number;
   attempt_number: number;
   status: 'passed' | 'failed';
   marks_obtained?: number | null;
   lines_correct?: number | null;
   lines_total?: number | null;
   lines_percent?: number | null;
   submitted_at?: string | null;
   trainer_feedback?: string | null;
   takeoff_pdf_name?: string | null;
   boq_xlsx_name?: string | null;
   has_pdf: boolean;
   has_excel: boolean;
   grading_breakdown?: QuantityTakeoffBreakdownLine[] | null;
   user?: { id: number; name: string; email: string } | null;
}

interface Props extends SharedData {
   course: { id: number; title: string };
   plan: UsExperiencePlan;
   attempt: TrainerAttemptDetail;
}

const formatWhen = (value?: string | null) => {
   if (!value) {
      return '—';
   }

   const date = new Date(value);

   return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
};

const AttemptReview = ({ course, plan, attempt }: Props) => {
   const form = useForm({
      trainer_feedback: attempt.trainer_feedback ?? '',
   });

   const saveFeedback = (event: FormEvent) => {
      event.preventDefault();
      form.put(
         route('courses.us-experience.attempts.feedback', {
            course: course.id,
            plan: plan.id,
            attempt: attempt.id,
         }),
         { preserveScroll: true },
      );
   };

   const downloadHref = (file: 'pdf' | 'excel') =>
      route('courses.us-experience.attempts.download', {
         course: course.id,
         plan: plan.id,
         attempt: attempt.id,
         file,
      });

   return (
      <div className="space-y-6">
         <div>
            <p className="text-muted-foreground text-sm">{course.title}</p>
            <UsExperiencePlanWorkspaceHeader courseId={course.id} plan={plan} current="attempts" />
         </div>

         <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
               <h1 className="text-2xl font-bold">
                  {attempt.user?.name ?? 'Student'} · Attempt #{attempt.attempt_number}
               </h1>
               <p className="text-muted-foreground text-sm">{attempt.user?.email}</p>
               <p className="text-muted-foreground mt-1 text-sm">Submitted {formatWhen(attempt.submitted_at)}</p>
            </div>
            <div className="flex flex-wrap items-center gap-2">
               <Badge variant={attempt.status === 'passed' ? 'default' : 'destructive'}>
                  {attempt.status === 'passed' ? 'Passed' : 'Failed'}
               </Badge>
               <Button variant="outline" asChild>
                  <Link href={route('courses.us-experience.attempts.index', { course: course.id, plan: plan.id })}>Plan attempts</Link>
               </Button>
               <Button variant="outline" asChild>
                  <Link href={route('courses.us-experience.attempts.course', course.id)}>Course attempts</Link>
               </Button>
            </div>
         </div>

         <Card>
            <CardHeader>
               <CardTitle>Submitted files</CardTitle>
               <CardDescription>Downloads go through your trainer login. Grading used the Excel BOQ.</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-wrap gap-3">
               {attempt.has_pdf ? (
                  <Button asChild>
                     <a href={downloadHref('pdf')}>
                        <Download className="mr-2 h-4 w-4" />
                        {attempt.takeoff_pdf_name || 'Takeoff PDF'}
                     </a>
                  </Button>
               ) : (
                  <p className="text-muted-foreground text-sm">No takeoff PDF on this attempt.</p>
               )}
               {attempt.has_excel ? (
                  <Button variant="outline" asChild>
                     <a href={downloadHref('excel')}>
                        <Download className="mr-2 h-4 w-4" />
                        {attempt.boq_xlsx_name || 'Excel BOQ'}
                     </a>
                  </Button>
               ) : (
                  <p className="text-muted-foreground text-sm">No Excel BOQ on this attempt.</p>
               )}
            </CardContent>
         </Card>

         {attempt.grading_breakdown && attempt.grading_breakdown.length > 0 && (
            <Card className="p-4 sm:p-6">
               <QuantityTakeoffBreakdown
                  breakdown={attempt.grading_breakdown}
                  linesCorrect={attempt.lines_correct ?? undefined}
                  linesTotal={attempt.lines_total ?? undefined}
                  viewer="trainer"
               />
            </Card>
         )}

         <Card>
            <CardHeader>
               <CardTitle>Trainer feedback</CardTitle>
               <CardDescription>Optional note for this attempt. The student sees it on the plan after you save.</CardDescription>
            </CardHeader>
            <CardContent>
               <form onSubmit={saveFeedback} className="space-y-4">
                  <div className="space-y-2">
                     <Label htmlFor="trainer_feedback">Note</Label>
                     <Textarea
                        id="trainer_feedback"
                        rows={5}
                        value={form.data.trainer_feedback}
                        onChange={(event) => form.setData('trainer_feedback', event.target.value)}
                        placeholder="What to fix on the next attempt, or confirmation that the takeoff looks good."
                     />
                     <InputError message={form.errors.trainer_feedback} />
                  </div>
                  <LoadingButton type="submit" loading={form.processing}>
                     Save feedback
                  </LoadingButton>
               </form>
            </CardContent>
         </Card>
      </div>
   );
};

AttemptReview.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default AttemptReview;
