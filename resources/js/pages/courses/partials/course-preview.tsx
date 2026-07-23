import CourseBannerPlaceholder from '@/components/course-banner-placeholder';
import SubscriptionBillingNotice from '@/components/subscription-billing-notice';
import { Dialog, DialogContent, DialogTrigger } from '@/components/ui/dialog';
import VideoPlayer from '@/components/video-player';
import courseLanguages from '@/data/course-languages';
import { isSubscriptionCourse } from '@/lib/subscription-billing';
import { getCourseDuration, systemCurrency } from '@/lib/utils';
import { usePage } from '@inertiajs/react';
import { BarChart3, Calendar, Clock, Languages, Mail, Play, Users } from 'lucide-react';
import { CourseDetailsProps } from '../show';
import SsuEnrollmentPanel from '@/components/ssu-enrollment-panel';
import CourseLaunchNotifyForm from '@/components/course-launch-notify-form';
import EnrollOrPlayerButton from './course-player-button';

const CoursePreview = () => {
   const { course, system, translate } = usePage<CourseDetailsProps>().props;
   const { frontend } = translate;
   const currency = systemCurrency(system.fields['selling_currency']);
   const courseLanguage = courseLanguages.find((language) => language.value === course.language);
   const isSubscription = isSubscriptionCourse(course);

   return (
      <div className="ssu-enrollment-shell sticky top-24 space-y-5 p-5">
         <div className="space-y-4">
            <div className="relative">
               {course.thumbnail ? (
                  <img className="aspect-video w-full rounded-lg object-cover" src={course.thumbnail} alt={course.title} />
               ) : (
                  <CourseBannerPlaceholder title={course.title} className="aspect-video w-full rounded-lg" />
               )}

               {course.preview && (
                  <Dialog>
                     <DialogTrigger asChild>
                        <button className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 cursor-pointer rounded-full bg-black/70 p-4 transition-transform hover:scale-110">
                           <Play className="h-6 w-6 text-white" />
                        </button>
                     </DialogTrigger>

                     <DialogContent className="overflow-hidden p-0 md:min-w-3xl">
                        <VideoPlayer
                           source={{
                              type: 'video' as const,
                              sources: [
                                 {
                                    src: course.preview,
                                    type: 'video/mp4' as const,
                                 },
                              ],
                           }}
                        />
                     </DialogContent>
                  </Dialog>
               )}
            </div>

            <h2 className="font-display text-primary text-3xl font-bold capitalize">
               {course.pricing_type === 'free' ? (
                  course.pricing_type
               ) : course.billing_model === 'subscription' ? (
                  <>
                     <span className="font-semibold">
                        {currency?.symbol}
                        {course.subscription_price ?? course.price}
                     </span>
                     <span className="text-muted-foreground ml-2 text-base font-medium">/month</span>
                  </>
               ) : course.discount ? (
                  <>
                     <span className="font-semibold">
                        {currency?.symbol}
                        {course.discount_price}
                     </span>
                     <span className="text-muted-foreground ml-2 text-base font-medium line-through">
                        {currency?.symbol}
                        {course.price}
                     </span>
                  </>
               ) : (
                  <>
                     <span className="font-semibold">
                        {currency?.symbol}
                        {course.price}
                     </span>
                  </>
               )}
            </h2>

            {isSubscription ? <SubscriptionBillingNotice course={course} variant="detail" /> : null}

            <SsuEnrollmentPanel isSubscription={isSubscription}>
               <EnrollOrPlayerButton />
            </SsuEnrollmentPanel>

            <CourseLaunchNotifyForm />
         </div>

         <div className="divide-border/60 mt-1 divide-y border-t pt-1">
            <div className="flex items-center justify-between py-2.5 text-sm">
               <span className="text-muted-foreground flex items-center gap-2">
                  <Users className="text-primary h-4.5 w-4.5" />
                  {frontend.students}
               </span>
               <span className="font-medium">{course.enrollments_count || 0}</span>
            </div>

            <div className="flex items-center justify-between py-2.5 text-sm">
               <span className="text-muted-foreground flex items-center gap-2">
                  <Languages className="text-primary h-4.5 w-4.5" />
                  {frontend.language}
               </span>
               <span className="font-medium">{courseLanguage?.label}</span>
            </div>

            <div className="flex items-center justify-between py-2.5 text-sm">
               <span className="text-muted-foreground flex items-center gap-2">
                  <Clock className="text-primary h-4.5 w-4.5" />
                  {frontend.duration}
               </span>
               <span className="font-medium">{getCourseDuration(course)}</span>
            </div>

            <div className="flex items-center justify-between py-2.5 text-sm">
               <span className="text-muted-foreground flex items-center gap-2">
                  <BarChart3 className="text-primary h-4.5 w-4.5" />
                  {frontend.level}
               </span>
               <span className="font-medium capitalize">{course.level}</span>
            </div>

            <div className="flex items-center justify-between py-2.5 text-sm">
               <span className="text-muted-foreground flex items-center gap-2">
                  <Calendar className="text-primary h-4.5 w-4.5" />
                  {frontend.expiry_period}
               </span>
               <span className="font-medium capitalize">{course.expiry_type === 'lifetime' ? 'lifetime' : course?.expiry_duration}</span>
            </div>

            <div className="flex items-center justify-between py-2.5 text-sm">
               <span className="text-muted-foreground flex items-center gap-2">
                  <Mail className="text-primary h-4.5 w-4.5" />
                  {frontend.certificate_included}
               </span>
               <span className="font-medium">Yes</span>
            </div>
         </div>
      </div>
   );
};

export default CoursePreview;
