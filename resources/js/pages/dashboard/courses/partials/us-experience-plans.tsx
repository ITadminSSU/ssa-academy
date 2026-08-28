import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { CourseUpdateProps } from '../update';
import { Link, router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Pencil, Plus, Trash2 } from 'lucide-react';
import UsExperiencePlanForm from './forms/us-experience-plan-form';
import UsExperiencePlanEditor from './us-experience-plan-editor';

const UsExperiencePlans = () => {
   const { course, usExperiencePlans = [], usExperiencePlan, translate } = usePage<CourseUpdateProps>().props;

   if (usExperiencePlan) {
      return <UsExperiencePlanEditor />;
   }

   const plans = usExperiencePlans;

   const move = (plan: UsExperiencePlan, direction: 'up' | 'down') => {
      router.post(route('courses.us-experience.move', { course: course.id, plan: plan.id }), { direction }, { preserveScroll: true });
   };

   const remove = (plan: UsExperiencePlan) => {
      if (!confirm(`Delete “${plan.title}”? Student attempts for this plan will also be removed.`)) {
         return;
      }

      router.delete(route('courses.us-experience.destroy', { course: course.id, plan: plan.id }), { preserveScroll: true });
   };

   return (
      <Card className="space-y-6 overflow-hidden p-4 sm:p-6">
         <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
               <h2 className="text-xl font-bold">{translate.button.build_your_us_experience ?? 'Build Your US Experience'}</h2>
               <p className="text-muted-foreground mt-1 max-w-2xl text-sm">
                  Practice takeoff plans. Students finish Plan 1 (Passed) before Plan 2 unlocks, same idea as lessons. This is not a timed exam
                  and does not block the certificate.
               </p>
            </div>
            <UsExperiencePlanForm
               handler={
                  <Button className="flex items-center gap-2">
                     <Plus className="h-4 w-4" />
                     Add plan
                  </Button>
               }
            />
         </div>

         <Table className="border-border min-w-3xl border-y">
            <TableHeader>
               <TableRow>
                  <TableHead className="w-24">Order</TableHead>
                  <TableHead>Group</TableHead>
                  <TableHead>Plan</TableHead>
                  <TableHead>Pass / attempts</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
               </TableRow>
            </TableHeader>
            <TableBody>
               {plans.length ? (
                  plans.map((plan, index) => (
                     <TableRow key={plan.id}>
                        <TableCell>
                           <div className="flex items-center gap-1">
                              <Button type="button" size="icon" variant="ghost" disabled={index === 0} onClick={() => move(plan, 'up')}>
                                 <ArrowUp className="h-4 w-4" />
                              </Button>
                              <Button
                                 type="button"
                                 size="icon"
                                 variant="ghost"
                                 disabled={index === plans.length - 1}
                                 onClick={() => move(plan, 'down')}
                              >
                                 <ArrowDown className="h-4 w-4" />
                              </Button>
                           </div>
                        </TableCell>
                        <TableCell>
                           <p className="font-medium">{plan.group_name}</p>
                        </TableCell>
                        <TableCell>
                           <p className="font-medium">{plan.title}</p>
                           <p className="text-muted-foreground text-xs">
                              {plan.drawings_count ?? 0} drawing(s) · {plan.line_count ?? 0} key line(s)
                           </p>
                        </TableCell>
                        <TableCell>
                           {plan.pass_mark}% / {plan.max_attempts}
                        </TableCell>
                        <TableCell>
                           <div className="flex flex-wrap gap-1">
                              {plan.published ? <Badge>Published</Badge> : <Badge variant="outline">Draft</Badge>}
                              {plan.is_ready ? (
                                 <Badge variant="secondary">Ready</Badge>
                              ) : (
                                 <Badge variant="destructive">Needs files</Badge>
                              )}
                           </div>
                        </TableCell>
                        <TableCell className="text-right">
                           <div className="flex justify-end gap-1">
                              <Button type="button" size="icon" variant="ghost" asChild>
                                 <Link href={route('courses.edit', { course: course.id, tab: 'us-experience', plan: plan.id })}>
                                    <Pencil className="h-4 w-4" />
                                 </Link>
                              </Button>
                              <Button type="button" size="icon" variant="ghost" onClick={() => remove(plan)}>
                                 <Trash2 className="h-4 w-4" />
                              </Button>
                           </div>
                        </TableCell>
                     </TableRow>
                  ))
               ) : (
                  <TableRow>
                     <TableCell colSpan={6} className="text-muted-foreground h-24 text-center">
                        No plans yet. Add a plan, then upload PDF drawings, the blank Excel, and the answer key.
                     </TableCell>
                  </TableRow>
               )}
            </TableBody>
         </Table>
      </Card>
   );
};

export default UsExperiencePlans;
