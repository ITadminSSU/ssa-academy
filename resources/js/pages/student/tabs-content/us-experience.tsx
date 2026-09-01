import QuantityTakeoffBreakdown from '@/components/exam/quantity-takeoff-breakdown';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { StudentCourseProps } from '@/types/page';
import { usePage } from '@inertiajs/react';
import { Download, Lock } from 'lucide-react';
import UsExperienceSubmitDialog from '../partials/us-experience-submit-dialog';

const statusTone = (status: UsExperienceStudentPlan['status']) => {
   switch (status) {
      case 'passed':
         return 'default';
      case 'failed':
         return 'destructive';
      case 'locked':
         return 'outline';
      default:
         return 'secondary';
   }
};

const statusLabel = (status: UsExperienceStudentPlan['status']) => {
   switch (status) {
      case 'passed':
         return 'Passed';
      case 'failed':
         return 'Failed';
      case 'locked':
         return 'Locked';
      default:
         return 'Ongoing';
   }
};

const groupPlans = (plans: UsExperienceStudentPlan[]) => {
   const groups: { name: string; description?: string | null; plans: UsExperienceStudentPlan[] }[] = [];

   plans.forEach((plan) => {
      const last = groups[groups.length - 1];
      if (last && last.name === plan.group_name) {
         last.plans.push(plan);
         return;
      }
      groups.push({
         name: plan.group_name,
         description: plan.group_description,
         plans: [plan],
      });
   });

   return groups;
};

const UsExperience = () => {
   const { usExperience, subscriptionAccess } = usePage<StudentCourseProps>().props;
   const payload = usExperience;
   const plans = payload?.plans ?? [];
   const groups = groupPlans(plans);
   const lapsed = subscriptionAccess?.mode === 'completed_only';

   if (!payload || plans.length === 0) {
      return (
         <p className="text-muted-foreground text-sm">
            Practice takeoff plans will appear here when your trainer publishes them. Finish each plan (Passed) to unlock the next one.
         </p>
      );
   }

   return (
      <div className="space-y-4">
         {lapsed && (
            <Alert>
               <Lock className="h-4 w-4" />
               <AlertTitle>Downloads and submissions are locked</AlertTitle>
               <AlertDescription>
                  Your scores stay visible. Resubscribe to download drawings and submit another attempt.
               </AlertDescription>
            </Alert>
         )}

         <p className="text-muted-foreground text-sm">
            Download the PDF plans and blank Excel, fill the Quantity Summary, then submit your takeoff PDF and BOQ. Default variance
            is ±{payload.default_tolerance}. Pass a plan to unlock the next.
         </p>

         <Accordion type="multiple" defaultValue={groups.slice(0, 1).map((group, index) => `${group.name}-${index}`)} className="w-full">
            {groups.map((group, groupIndex) => (
               <AccordionItem key={`${group.name}-${groupIndex}`} value={`${group.name}-${groupIndex}`}>
                  <AccordionTrigger className="text-base font-semibold">{group.name}</AccordionTrigger>
                  <AccordionContent className="space-y-3">
                     {group.description && <p className="text-muted-foreground text-sm">{group.description}</p>}
                     {group.plans.map((plan) => (
                        <div key={plan.id} className="rounded-lg border p-4">
                           <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                              <div>
                                 <div className="flex flex-wrap items-center gap-2">
                                    <p className="font-medium">{plan.title}</p>
                                    <Badge variant={statusTone(plan.status)}>{statusLabel(plan.status)}</Badge>
                                 </div>
                                 <p className="text-muted-foreground mt-1 text-sm">
                                    Attempts {plan.attempts_used}/{plan.max_attempts}
                                    {payload.can_see_scores && plan.accuracy != null ? ` · Accuracy ${plan.accuracy}%` : ''}
                                    {` · Pass ${plan.pass_mark}%`}
                                 </p>
                              </div>
                              <div className="flex flex-wrap gap-2">
                                 {plan.can_download ? (
                                    <Button size="sm" variant="outline" asChild>
                                       <a href={route('us-experience.pack', plan.id)}>
                                          <Download className="h-4 w-4" />
                                          Download pack
                                       </a>
                                    </Button>
                                 ) : (
                                    <Button size="sm" variant="outline" disabled>
                                       <Lock className="h-4 w-4" />
                                       {plan.status === 'locked' ? 'Locked' : 'Download locked'}
                                    </Button>
                                 )}
                                 {plan.can_submit ? (
                                    <UsExperienceSubmitDialog plan={plan} />
                                 ) : plan.status === 'passed' ? (
                                    <Button size="sm" variant="secondary" disabled>
                                       Passed
                                    </Button>
                                 ) : null}
                              </div>
                           </div>

                           {payload.can_see_scores && plan.latest_attempt?.grading_breakdown && (
                              <div className="mt-4">
                                 <QuantityTakeoffBreakdown
                                    breakdown={plan.latest_attempt.grading_breakdown}
                                    linesCorrect={plan.latest_attempt.lines_correct ?? undefined}
                                    linesTotal={plan.latest_attempt.lines_total ?? undefined}
                                 />
                              </div>
                           )}

                           {plan.tutorial_video && (
                              <div className="mt-3">
                                 <p className="mb-2 text-sm font-medium">Walkthrough</p>
                                 <video src={plan.tutorial_video.url} controls className="max-h-80 w-full rounded-md bg-black" />
                              </div>
                           )}
                        </div>
                     ))}
                  </AccordionContent>
               </AccordionItem>
            ))}
         </Accordion>
      </div>
   );
};

export default UsExperience;
