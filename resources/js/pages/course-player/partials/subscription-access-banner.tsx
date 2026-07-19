import SsuCheckoutButton from '@/components/ssu-checkout-button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { CoursePlayerProps, StudentCourseProps } from '@/types/page';
import { Link, usePage } from '@inertiajs/react';
import { Lock } from 'lucide-react';

interface Props {
   subscriptionAccess?: SubscriptionAccess;
   course?: Course;
}

const SubscriptionAccessBanner = ({ subscriptionAccess: accessProp, course: courseProp }: Props = {}) => {
   const page = usePage<CoursePlayerProps | StudentCourseProps>().props;
   const subscriptionAccess = accessProp ?? page.subscriptionAccess;
   const course = courseProp ?? page.course;

   if (!subscriptionAccess || subscriptionAccess.mode !== 'completed_only' || !course) {
      return null;
   }

   return (
      <Alert className="border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-50">
         <Lock className="h-4 w-4" />
         <AlertTitle>Subscription inactive</AlertTitle>
         <AlertDescription className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <span>
               Completed lessons stay available in read-only mode. New content is locked until you resubscribe.
            </span>

            {subscriptionAccess.can_resubscribe ? (
               <SsuCheckoutButton item="course" item_id={course.id} className="sm:w-auto">
                  Resubscribe
               </SsuCheckoutButton>
            ) : (
               <Button asChild variant="outline" size="sm" className="sm:w-auto">
                  <Link href={route('courses.show', { slug: course.slug, id: course.id })}>View course</Link>
               </Button>
            )}
         </AlertDescription>
      </Alert>
   );
};

export default SubscriptionAccessBanner;
