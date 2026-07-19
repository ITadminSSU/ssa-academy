import SsuCheckoutButton from '@/components/ssu-checkout-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { StudentDashboardProps } from '@/types/page';
import { Link, usePage } from '@inertiajs/react';
import { CreditCard, ExternalLink, Lock, RefreshCw } from 'lucide-react';

const statusLabel: Record<SubscriptionStatus, string> = {
   trialing: 'Trial',
   active: 'Active',
   past_due: 'Past due',
   canceled: 'Canceled',
   unpaid: 'Unpaid',
   paused: 'Paused',
};

const statusVariant = (status: SubscriptionStatus, grantsFullAccess: boolean): 'default' | 'secondary' | 'destructive' | 'outline' => {
   if (grantsFullAccess) {
      return 'default';
   }

   if (status === 'past_due') {
      return 'destructive';
   }

   if (status === 'canceled') {
      return 'secondary';
   }

   return 'outline';
};

const formatDate = (value?: string | null) => {
   if (!value) {
      return null;
   }

   return new Date(value).toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
   });
};

const MySubscriptions = () => {
   const { subscriptions = [], canManageBilling } = usePage<StudentDashboardProps>().props;

   return (
      <div className="space-y-6">
         <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
               <h1 className="text-2xl font-bold tracking-tight">My Subscriptions</h1>
               <p className="text-muted-foreground mt-1 text-sm">
                  Manage monthly course subscriptions, billing, and cancellations.
               </p>
            </div>

            {canManageBilling ? (
               <Button asChild variant="outline" className="shrink-0">
                  <a href={route('subscriptions.portal')}>
                     <CreditCard className="mr-2 h-4 w-4" />
                     Manage billing
                     <ExternalLink className="ml-2 h-3.5 w-3.5 opacity-60" />
                  </a>
               </Button>
            ) : null}
         </div>

         {subscriptions.length > 0 ? (
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
               {subscriptions.map((subscription) => {
                  const course = subscription.course;
                  const periodEnd = formatDate(subscription.current_period_end);
                  const graceEnds = formatDate(subscription.grace_ends_at);
                  const isReadOnly = !subscription.grants_full_access;
                  const showResubscribe = isReadOnly && course;

                  return (
                     <Card key={subscription.id} className="overflow-hidden">
                        <CardHeader className="pb-4">
                           <div className="flex items-start gap-4">
                              {course?.thumbnail ? (
                                 <img
                                    src={course.thumbnail}
                                    alt={course.title}
                                    className="h-16 w-24 shrink-0 rounded-md object-cover"
                                 />
                              ) : (
                                 <div className="bg-muted flex h-16 w-24 shrink-0 items-center justify-center rounded-md">
                                    <CreditCard className="text-muted-foreground h-6 w-6" />
                                 </div>
                              )}

                              <div className="min-w-0 flex-1">
                                 <CardTitle className="line-clamp-2 text-lg">{course?.title ?? 'Course subscription'}</CardTitle>
                                 <CardDescription className="mt-1">
                                    {course?.subscription_price != null
                                       ? `$${Number(course.subscription_price).toFixed(2)}/month`
                                       : 'Monthly subscription'}
                                 </CardDescription>
                              </div>
                           </div>
                        </CardHeader>

                        <CardContent className="space-y-3">
                           <div className="flex flex-wrap gap-2">
                              <Badge variant={statusVariant(subscription.status, subscription.grants_full_access)}>
                                 {statusLabel[subscription.status] ?? subscription.status}
                              </Badge>

                              {subscription.cancel_at_period_end ? (
                                 <Badge variant="outline">Cancels at period end</Badge>
                              ) : null}

                              {isReadOnly ? (
                                 <Badge variant="secondary">
                                    <Lock className="mr-1 h-3 w-3" />
                                    Read-only access
                                 </Badge>
                              ) : null}
                           </div>

                           {periodEnd ? (
                              <p className="text-muted-foreground text-sm">
                                 {subscription.cancel_at_period_end ? 'Access until' : 'Renews on'}: {periodEnd}
                              </p>
                           ) : null}

                           {graceEnds && subscription.status === 'past_due' ? (
                              <p className="text-destructive text-sm">Payment grace period ends: {graceEnds}</p>
                           ) : null}

                           {isReadOnly ? (
                              <p className="text-muted-foreground text-sm">
                                 Completed lessons remain available. Resubscribe to unlock new content and continue learning.
                              </p>
                           ) : null}
                        </CardContent>

                        <CardFooter className="flex flex-wrap gap-2 border-t pt-4">
                           {course ? (
                              <Button asChild variant="outline" size="sm">
                                 <Link href={route('courses.show', { slug: course.slug, id: course.id })}>View course</Link>
                              </Button>
                           ) : null}

                           {course && subscription.grants_full_access ? (
                              <Button asChild size="sm">
                                 <Link href={route('student.course.show', { id: course.id, tab: 'modules' })}>Continue learning</Link>
                              </Button>
                           ) : null}

                           {showResubscribe && course ? (
                              <SsuCheckoutButton item="course" item_id={course.id} className="sm:w-auto">
                                 <RefreshCw className="mr-2 h-4 w-4" />
                                 Resubscribe
                              </SsuCheckoutButton>
                           ) : null}
                        </CardFooter>
                     </Card>
                  );
               })}
            </div>
         ) : (
            <Card className="flex flex-col items-center justify-center gap-3 p-10 text-center">
               <CreditCard className="text-muted-foreground h-10 w-10" />
               <div>
                  <p className="font-medium">No subscriptions yet</p>
                  <p className="text-muted-foreground mt-1 text-sm">
                     When you subscribe to a monthly course, it will appear here for easy billing management.
                  </p>
               </div>
               <Button asChild variant="outline">
                  <Link href={route('student.category.courses', { category: 'all' })}>Browse courses</Link>
               </Button>
            </Card>
         )}

         {canManageBilling && subscriptions.length > 0 ? (
            <Card className="border-dashed">
               <CardContent className="flex flex-col gap-3 p-6 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                     <p className="font-medium">Stripe Customer Portal</p>
                     <p className="text-muted-foreground text-sm">
                        Update your card, view invoices, or cancel subscriptions securely through Stripe.
                     </p>
                  </div>
                  <Button asChild>
                     <a href={route('subscriptions.portal')}>Open billing portal</a>
                  </Button>
               </CardContent>
            </Card>
         ) : null}
      </div>
   );
};

export default MySubscriptions;
