import CourseBannerPlaceholder from '@/components/course-banner-placeholder';
import SubscriptionBillingNotice from '@/components/subscription-billing-notice';
import { Dialog, DialogContent, DialogTrigger } from '@/components/ui/dialog';
import VideoPlayer from '@/components/video-player';
import courseLanguages from '@/data/course-languages';
import { formatCatalogPromoDate, formatCatalogPromoDeadline, formatOfferAmount, getLaunchOfferView } from '@/lib/launch-offer';
import { isSubscriptionCourse, isUpfrontSubscriptionCourse } from '@/lib/subscription-billing';
import { getCourseDuration, systemCurrency } from '@/lib/utils';
import { usePage } from '@inertiajs/react';
import { BarChart3, Calendar, Clock, Languages, Mail, Play, Users } from 'lucide-react';
import { CourseDetailsProps } from '../show';
import SsuEnrollmentPanel from '@/components/ssu-enrollment-panel';
import CourseLaunchNotifyForm from '@/components/course-launch-notify-form';
import EnrollOrPlayerButton from './course-player-button';

const CoursePreview = () => {
   const { course, system, translate, launchOffer, enrollment } = usePage<CourseDetailsProps>().props;
   const { frontend } = translate;
   const currency = systemCurrency(system.fields['selling_currency']);
   const courseLanguage = courseLanguages.find((language) => language.value === course.language);
   const isSubscription = isSubscriptionCourse(course);
   const isUpfrontSubscription = isUpfrontSubscriptionCourse(course);
   const offer = getLaunchOfferView(course, launchOffer, enrollment);
   const catalogPromo = offer.catalogPromo;
   const promoWindowEnd = catalogPromo?.window_end ?? course.launch_offer_ends_at;
   const promoDeadline = formatCatalogPromoDeadline(promoWindowEnd);
   const promoWindowDate = formatCatalogPromoDate(promoWindowEnd);
   const thenFullPrice = formatOfferAmount(catalogPromo?.full_upfront_price ?? offer.fullUpfrontPrice);
   const symbol = currency?.symbol ?? '$';

   return (
      <div className="ssu-enrollment-shell sticky top-24 space-y-5 p-5">
         <div className="space-y-4">
            <div className="relative isolate overflow-clip rounded-lg">
               {course.thumbnail ? (
                  <img className="aspect-video w-full object-cover" src={course.thumbnail} alt={course.title} />
               ) : (
                  <CourseBannerPlaceholder title={course.title} className="aspect-video w-full" />
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
               ) : offer.enabled && offer.phase === 'pre_register' ? (
                  catalogPromo ? (
                     <>
                        <span className="text-muted-foreground text-xl font-medium line-through">
                           {symbol}
                           {formatOfferAmount(catalogPromo.list_price)} course price
                        </span>
                        <span className="mt-1 block">
                           {symbol}
                           {formatOfferAmount(catalogPromo.total_with_coupon)}{' '}
                           <span className="text-muted-foreground text-base font-medium normal-case">with coupon</span>
                        </span>
                     </>
                  ) : (
                     <>
                        <span className="font-semibold">
                           {symbol}
                           {offer.listPrice}
                        </span>
                        <span className="text-muted-foreground ml-2 text-base font-medium normal-case">
                           course price
                        </span>
                     </>
                  )
               ) : offer.enabled && offer.phase === 'full_price' ? (
                  catalogPromo ? (
                     <>
                        <span className="text-muted-foreground text-xl font-medium line-through">
                           {symbol}
                           {formatOfferAmount(catalogPromo.full_upfront_price)}
                        </span>
                        <span className="mt-1 block">
                           {symbol}
                           {formatOfferAmount(catalogPromo.full_upfront_with_coupon)}{' '}
                           <span className="text-muted-foreground text-base font-medium normal-case">with coupon</span>
                        </span>
                     </>
                  ) : (
                     <>
                        <span className="font-semibold">
                           {symbol}
                           {offer.fullUpfrontPrice}
                        </span>
                        <span className="text-muted-foreground ml-2 text-base font-medium">
                           + {symbol}
                           {offer.subscriptionPrice}/mo
                        </span>
                     </>
                  )
               ) : isUpfrontSubscription ? (
                  <>
                     <span className="font-semibold">
                        {currency?.symbol}
                        {course.price}
                     </span>
                     <span className="text-muted-foreground ml-2 text-base font-medium">
                        + {currency?.symbol}
                        {course.subscription_price}/mo
                     </span>
                  </>
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

            {offer.enabled && offer.phase === 'pre_register' ? (
               <div className="text-muted-foreground space-y-3 text-sm leading-relaxed">
                  {catalogPromo ? (
                     <p>
                        {symbol}
                        {formatOfferAmount(catalogPromo.deposit_amount)} to pre-register · {symbol}
                        {formatOfferAmount(catalogPromo.balance_amount)} at launch day or {symbol}
                        {formatOfferAmount(catalogPromo.balance_with_coupon)} using coupons · {symbol}
                        {formatOfferAmount(catalogPromo.subscription_price)} monthly subscription. Enter code at
                        checkout{promoDeadline ? ` ${promoDeadline}` : ''}, then {symbol}
                        {thenFullPrice}. Coupons do not apply to the deposit.
                     </p>
                  ) : (
                     <p>
                        Reserve your seat for {symbol}
                        {offer.depositAmount} today, then pay {symbol}
                        {offer.balanceAmount} on launch day (or within the 5-day grace period) for full access and one month of
                        Project Plans Subscription for FREE, starting from the day you pay the balance. After that free month,
                        the subscription is {symbol}
                        {offer.subscriptionPrice}/month. If you miss the grace deadline, the free month is cancelled and you
                        can only enroll later at the full upfront price of {symbol}
                        {thenFullPrice} after {promoWindowDate ?? 'the pre-register window'}. Cancel project plans subscription anytime.
                     </p>
                  )}
                  <p>
                     Please note: The {symbol}
                     {offer.depositAmount} deposit is non-refundable.
                  </p>
                  <p>
                     Keep an eye out for discount vouchers on our{' '}
                     <a
                        href="https://www.facebook.com/smartsourcingusa"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="font-medium text-blue-600 underline underline-offset-2 hover:text-blue-700"
                     >
                        official Facebook page
                     </a>{' '}
                     or from your referrer for huge savings!
                  </p>
               </div>
            ) : offer.enabled && offer.phase === 'full_price' ? (
               <p className="text-muted-foreground text-sm">
                  Pay the full price of {currency?.symbol}
                  {offer.fullUpfrontPrice} now, plus the first month of Project Plans ({currency?.symbol}
                  {offer.subscriptionPrice}). That is not a free month. Monthly billing continues after that. Cancel anytime
                  from My Subscriptions.
               </p>
            ) : isUpfrontSubscription ? (
               <p className="text-muted-foreground text-sm">
                  Pay {currency?.symbol}
                  {course.price} now, plus the first month of Project Plans ({currency?.symbol}
                  {course.subscription_price}). Monthly billing continues after that. Cancel anytime from My Subscriptions.
               </p>
            ) : null}

            {isSubscription && !(offer.enabled && (offer.phase === 'pre_register' || offer.phase === 'full_price')) ? (
               <SubscriptionBillingNotice course={course} variant="detail" />
            ) : null}

            <SsuEnrollmentPanel
               isSubscription={isSubscription && offer.phase !== 'full_price'}
               enrollmentNote={
                  offer.enabled && offer.phase === 'full_price'
                     ? `Pay the full upfront price plus the first ${currency?.symbol ?? '$'}${offer.subscriptionPrice}/mo of Project Plans today (not a free month). Monthly billing continues after that.`
                     : offer.enabled && offer.phase === 'pre_register'
                       ? 'Reserve your seat by paying the deposit. Pay the balance by launch day or within the five-day grace period to activate access and get one month free subscription. Monthly billing starts afterward.'
                       : undefined
               }
            >
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
