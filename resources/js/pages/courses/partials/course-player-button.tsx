import SsuCheckoutButton from '@/components/ssu-checkout-button';
import { Button } from '@/components/ui/button';
import { canEnrollCourseWithoutPayment, requiresCoursePayment } from '@/lib/learner-access';
import { SharedData } from '@/types/global';
import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CourseDetailsProps } from '../show';

// Initializes the watch history for an enrolled user (or course-owner) and
// drops them into the player. Used when no watch history exists yet.
const StartCourseButton = () => {
   const { course, translate } = usePage<CourseDetailsProps>().props;
   const { frontend } = translate;
   const [loading, setLoading] = useState(false);

   return (
      <Button
         size="lg"
         className="w-full"
         disabled={loading}
         onClick={() => {
            setLoading(true);
            router.post(route('player.init.watch-history'), { course_id: course.id }, { onFinish: () => setLoading(false) });
         }}
      >
         {frontend.play_course}
      </Button>
   );
};

// Separate component for the play button to reduce duplication
const EnabledPlayButton = ({ watchHistory }: { watchHistory: WatchHistory }) => {
   const { props } = usePage<SharedData>();
   const { translate } = props;
   const { frontend } = translate;

   return (
      <Button size="lg" className="w-full" asChild>
         <Link
            href={route('course.player', {
               type: watchHistory.current_watching_type,
               watch_history: watchHistory.id,
               lesson_id: watchHistory.current_watching_id,
            })}
         >
            {frontend.play_course}
         </Link>
      </Button>
   );
};

// Disabled play button component
const DisabledPlayButton = () => {
   const { auth, course, approvalStatus, translate } = usePage<CourseDetailsProps>().props;
   const { frontend } = translate;
   const approve_able = approvalStatus.approve_able;

   return approve_able ? (
      <>
         {auth.user.role === 'instructor' ? (
            course.instructor_id === auth.user.instructor_id ? (
               <Button size="lg" className="w-full" onClick={() => router.post(route('player.init.watch-history'), { course_id: course.id })}>
                  {frontend.play_course}
               </Button>
            ) : (
               <EnrollmentButton />
            )
         ) : (
            <Button size="lg" className="w-full" onClick={() => router.post(route('player.init.watch-history'), { course_id: course.id })}>
               {frontend.play_course}
            </Button>
         )}
      </>
   ) : (
      <Button disabled size="lg" className="w-full">
         {frontend.course_player}
      </Button>
   );
};

const checkoutLabel = (course: Course, resubscribe: boolean) => {
   if (resubscribe) {
      return 'Resubscribe';
   }

   return course.billing_model === 'subscription' ? 'Subscribe now' : undefined;
};

// Enrollment/Buy button component
const EnrollmentButton = () => {
   const { auth, course, translate, wishlists, subscriptionAccess } = usePage<CourseDetailsProps>().props;
   const { frontend } = translate;
   const canResubscribe = subscriptionAccess?.can_resubscribe ?? false;
   const checkoutText = checkoutLabel(course, canResubscribe);

   const loginRedirectUrl = `${route('login')}?redirect=${encodeURIComponent(window.location.href)}`;

   const enrollmentHandler = (course: Course) => {
      if (!auth.user) {
         router.get(loginRedirectUrl);
         return;
      }

      router.post(route('enrollments.store'), {
         user_id: auth.user.id,
         course_id: course.id,
         enrollment_type: 'free',
      });
   };

   const isWishlisted = wishlists.find((wishlist) => wishlist.course_id === course.id);

   const handleWishlist = () => {
      if (isWishlisted) {
         router.delete(route('course-wishlists.destroy', { id: isWishlisted.id }));
      } else {
         router.post(route('course-wishlists.store', { user_id: auth.user?.id, course_id: course.id }));
      }
   };

   return (
      <>
         <Button className="w-full px-1 sm:px-3" variant="outline" size="lg" onClick={handleWishlist}>
            {isWishlisted ? frontend.remove_from_wishlist : frontend.add_to_wishlist}
         </Button>

         {requiresCoursePayment(auth.user, course) || canResubscribe ? (
            <SsuCheckoutButton item="course" item_id={course.id}>
               {checkoutText ?? frontend.buy_now}
            </SsuCheckoutButton>
         ) : canEnrollCourseWithoutPayment(auth.user, course) ? (
            <Button size="lg" className="w-full" onClick={() => enrollmentHandler(course)}>
               {frontend.enroll_now}
            </Button>
         ) : null}
      </>
   );
};

const EnrollOrPlayerButton = () => {
   const { auth, enrollment, watchHistory, subscriptionAccess, course } = usePage<CourseDetailsProps>().props;

   const isEnrolled = !!enrollment;
   const hasWatchHistory = !!watchHistory;
   const isAdminOrInstructor = auth.user && ['admin', 'instructor'].includes(auth.user.role);
   const canPlay = hasWatchHistory && (isAdminOrInstructor || (isEnrolled && subscriptionAccess?.mode !== 'none'));
   const showResubscribe = subscriptionAccess?.can_resubscribe ?? false;

   if (canPlay) {
      return (
         <div className="space-y-3">
            <EnabledPlayButton watchHistory={watchHistory} />
            {showResubscribe ? (
               <SsuCheckoutButton item="course" item_id={course.id}>
                  Resubscribe
               </SsuCheckoutButton>
            ) : null}
         </div>
      );
   }

   if (isAdminOrInstructor) {
      return <DisabledPlayButton />;
   }

   if (isEnrolled) {
      return <StartCourseButton />;
   }

   return <EnrollmentButton />;
};

export default EnrollOrPlayerButton;
