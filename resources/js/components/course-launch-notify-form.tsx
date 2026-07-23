import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { canPreviewCourseBeforeLaunch, isCourseComingSoon } from '@/lib/course-launch';
import { CourseDetailsProps } from '@/pages/courses/show';
import { useForm, usePage } from '@inertiajs/react';
import { Bell, CheckCircle2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const CourseLaunchNotifyForm = () => {
   const { course, auth, launchNotifySubscribed, translate } = usePage<CourseDetailsProps>().props;
   const { frontend, button, input } = translate;
   const comingSoon = isCourseComingSoon(course);
   const canStaffPreview = canPreviewCourseBeforeLaunch(course);
   const [subscribed, setSubscribed] = useState(Boolean(launchNotifySubscribed));

   const { data, setData, post, errors, processing } = useForm({
      email: auth.user?.email ?? '',
   });

   if (!comingSoon || canStaffPreview) {
      return null;
   }

   const submit: FormEventHandler = (e) => {
      e.preventDefault();

      post(route('course.launch-notifications.store', { course: course.id }), {
         preserveScroll: true,
         onSuccess: () => setSubscribed(true),
      });
   };

   if (subscribed) {
      return (
         <div className="flex items-start gap-3 rounded-lg border border-emerald-500/25 bg-emerald-500/5 p-4">
            <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
            <div className="space-y-1">
               <p className="text-sm font-medium text-emerald-900">{frontend.notify_subscribed_title ?? 'You are on the list'}</p>
               <p className="text-muted-foreground text-sm">
                  {frontend.notify_subscribed ?? 'We will email you when this course launches.'}
               </p>
            </div>
         </div>
      );
   }

   return (
      <div className="space-y-3 rounded-lg border border-amber-500/20 bg-amber-500/5 p-4">
         <div className="flex items-start gap-3">
            <Bell className="mt-0.5 h-5 w-5 shrink-0 text-amber-700" />
            <div className="space-y-1">
               <p className="text-sm font-medium">{frontend.notify_me_title ?? 'Notify me when available'}</p>
               <p className="text-muted-foreground text-sm">
                  {frontend.notify_me_description ?? 'Get an email as soon as enrollment opens for this course.'}
               </p>
            </div>
         </div>

         <form onSubmit={submit} className="flex flex-col gap-2 sm:flex-row">
            <Input
               type="email"
               name="email"
               value={data.email}
               onChange={(e) => setData('email', e.target.value)}
               placeholder={input.email_placeholder ?? 'Enter your email'}
               required
               className="bg-background"
            />
            <LoadingButton type="submit" loading={processing} className="shrink-0 sm:min-w-[140px]">
               {button.notify_me ?? 'Notify me'}
            </LoadingButton>
         </form>

         <InputError message={errors.email} />
      </div>
   );
};

export default CourseLaunchNotifyForm;
