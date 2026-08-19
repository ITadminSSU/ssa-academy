import SsuCheckoutButton from '@/components/ssu-checkout-button';
import { Button } from '@/components/ui/button';
import { canEnrollCourseWithoutPayment, requiresCoursePayment } from '@/lib/learner-access';
import { canPreviewCourseBeforeLaunch, formatCourseLaunchDateTime, isCourseComingSoon } from '@/lib/course-launch';
import { getLaunchOfferView } from '@/lib/launch-offer';
import { SharedData } from '@/types/global';
import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CourseDetailsProps } from '../show';

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

const checkoutLabel = (course: Course, resubscribe: boolean, launchLabel?: string) => {
   if (resubscribe) {
      return 'Resubscribe';
   }

   if (launchLabel) {
      return launchLabel;
   }

   if (course.billing_model === 'upfront_subscription') {
      return 'Enroll now';
   }

   return course.billing_model === 'subscription' ? 'Subscribe now' : undefined;
};

const ComingSoonButton = () => {
   const { course, translate } = usePage<CourseDetailsProps>().props;
   const { frontend } = translate;
   const launchLabel = formatCourseLaunchDateTime(course);

   return (
      <Button disabled size="lg" className="w-full">
         {launchLabel
            ? (frontend.available_on ?? 'Available on {date}').replace('{date}', launchLabel)
            : (frontend.coming_soon ?? 'Coming Soon')}
      </Button>
   );
};

const StaffPreviewButton = () => {
   const { course, watchHistory, translate } = usePage<CourseDetailsProps>().props;
   const { frontend, button } = translate;
   const [loading, setLoading] = useState(false);

   if (watchHistory) {
      return <EnabledPlayButton watchHistory={watchHistory} />;
   }

   return (
      <div className="space-y-3">
         <p className="text-muted-foreground text-center text-sm">
            {frontend.staff_preview_note ?? 'Staff preview — learners cannot access this course until launch.'}
         </p>
         <Button
            size="lg"
            className="w-full"
            disabled={loading}
            onClick={() => {
               setLoading(true);
               router.post(route('player.init.watch-history'), { course_id: course.id }, { onFinish: () => setLoading(false) });
            }}
         >
            {button.preview_course ?? 'Preview Course'}
         </Button>
      </div>
   );
};

const ReservedSeatPanel = () => {
   const { course, launchOffer, enrollment } = usePage<CourseDetailsProps>().props;
   const offer = getLaunchOfferView(course, launchOffer, enrollment);

   return (
      <div className="space-y-3">
         <p className="text-muted-foreground text-center text-sm">
            Seat reserved. Full access unlocks after you pay the remaining ${offer.balanceAmount.toFixed(0)}. Your $
            {offer.depositAmount.toFixed(0)} deposit is non-refundable.
         </p>
         {offer.canPayBalance ? (
            <SsuCheckoutButton item="course" item_id={course.id}>
               Pay remaining ${offer.balanceAmount.toFixed(0)}
            </SsuCheckoutButton>
         ) : (
            <Button disabled size="lg" className="w-full">
               Balance due on launch day
            </Button>
         )}
      </div>
   );
};

const EnrollmentButton = () => {
   const { auth, course, translate, wishlists, subscriptionAccess, launchOffer, enrollment } = usePage<CourseDetailsProps>().props;
   const { frontend } = translate;
   const canResubscribe = subscriptionAccess?.can_resubscribe ?? false;
   const offer = getLaunchOfferView(course, launchOffer, enrollment);

   let launchCheckoutLabel: string | undefined;
   if (offer.enabled && offer.canPreRegister) {
      launchCheckoutLabel = `Pre-register — $${offer.depositAmount.toFixed(0)}`;
   } else if (offer.enabled && offer.canFullEnroll) {
      launchCheckoutLabel = 'Pay full upfront price';
   }

   const checkoutText = checkoutLabel(course, canResubscribe, launchCheckoutLabel);
   const loginRedirectUrl = `${route('login')}?redirect=${encodeURIComponent(window.location.href)}`;

   const enrollmentHandler = (selectedCourse: Course) => {
      if (!auth.user) {
         router.get(loginRedirectUrl);
         return;
      }

      router.post(route('enrollments.store'), {
         user_id: auth.user.id,
         course_id: selectedCourse.id,
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

         {requiresCoursePayment(auth.user, course) || canResubscribe || offer.canPreRegister || offer.canFullEnroll ? (
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
   const { auth, enrollment, watchHistory, subscriptionAccess, course, launchOffer } = usePage<CourseDetailsProps>().props;

   const isEnrolled = !!enrollment && enrollment.access_status !== 'reserved' && enrollment.access_status !== 'canceled';
   const hasWatchHistory = !!watchHistory;
   const isAdminOrInstructor = auth.user && ['admin', 'instructor'].includes(auth.user.role);
   const comingSoon = isCourseComingSoon(course);
   const canStaffPreview = canPreviewCourseBeforeLaunch(course);
   const offer = getLaunchOfferView(course, launchOffer, enrollment);

   if (offer.reservedSeat) {
      return <ReservedSeatPanel />;
   }

   if (comingSoon && canStaffPreview) {
      return <StaffPreviewButton />;
   }

   if (comingSoon && (offer.canPreRegister || offer.canFullEnroll)) {
      return <EnrollmentButton />;
   }

   if (comingSoon) {
      return <ComingSoonButton />;
   }

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
