import { Button } from '@/components/ui/button';
import { CardContent, CardFooter, CardHeader } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import SubscriptionBillingNotice from '@/components/subscription-billing-notice';
import { formatCourseLaunchDate, formatLaunchCountdownShort, isCourseComingSoon } from '@/lib/course-launch';
import { isSubscriptionCourse } from '@/lib/subscription-billing';
import { cn, getCourseDuration, systemCurrency } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, Clock, Heart, Star, Users } from 'lucide-react';

interface Props {
   course: Course;
   viewType?: 'grid' | 'list';
   className?: string;
   wishlists?: CourseWishlist[];
}

const CourseCard1 = ({ course, viewType = 'grid', className, wishlists }: Props) => {
   const { props } = usePage<SharedData>();
   const { user } = props.auth;
   const { translate } = props;
   const { button, frontend, common } = translate;

   const isWishlisted = wishlists?.find((wishlist) => wishlist.course_id === course.id);
   const currency = systemCurrency(props.system.fields['selling_currency']);
   const detailsUrl = route('course.details', { slug: course.slug, id: course.id });
   const comingSoon = isCourseComingSoon(course);
   const launchDate = formatCourseLaunchDate(course);
   const countdownShort = comingSoon ? formatLaunchCountdownShort(course.launch_at) : null;
   const showPreviewCta = Boolean(course.can_preview_before_launch) && comingSoon;
   const isSubscription = isSubscriptionCourse(course);

   const handleWishlist = () => {
      if (isWishlisted) {
         router.delete(route('course-wishlists.destroy', { id: isWishlisted.id }));
      } else {
         router.post(route('course-wishlists.store', { user_id: user?.id, course_id: course.id }));
      }
   };

   return (
      <article
         className={cn(
            'ssu-course-card group flex flex-col',
            viewType === 'list' && 'sm:flex-row sm:items-stretch',
            className,
         )}
      >
         <CardHeader className="relative shrink-0 p-0">
            <Link href={detailsUrl} className="block">
               <div
                  className={cn(
                     'relative overflow-hidden',
                     viewType === 'list' ? 'aspect-[16/10] sm:aspect-auto sm:h-full sm:min-h-[200px] sm:w-[280px]' : 'aspect-[16/10]',
                  )}
               >
                  <img
                     src={course.thumbnail || '/assets/images/blank-image.jpg'}
                     alt={course.title}
                     className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                     onError={(e) => {
                        const target = e.target as HTMLImageElement;
                        target.src = '/assets/images/blank-image.jpg';
                     }}
                  />
                  <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-[oklch(0.22_0.04_255)]/50 via-transparent to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100" />

                  {comingSoon ? (
                     <span className="absolute top-3 right-3 z-[1] rounded-full bg-amber-400 px-2.5 py-0.5 text-[10px] font-bold tracking-wide text-amber-950 uppercase shadow-sm">
                        {frontend.coming_soon ?? 'Coming Soon'}
                     </span>
                  ) : null}

                  {course.pricing_type === 'free' && <span className="ssu-course-card__badge">{common.free}</span>}
                  {isSubscription && !comingSoon ? (
                     <span className="absolute top-3 right-3 z-[1] rounded-full border border-white/20 bg-card/95 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-[oklch(0.22_0.04_255)] uppercase backdrop-blur-sm">
                        {frontend.subscription_monthly_label ?? 'Monthly'}
                     </span>
                  ) : null}
                  {comingSoon && launchDate ? (
                     <span className="absolute bottom-3 right-3 z-[1] rounded-full border border-amber-200/80 bg-amber-50/95 px-2 py-0.5 text-[10px] font-semibold text-amber-900 backdrop-blur-sm">
                        {countdownShort ?? (frontend.launches_on ?? 'Launches {date}').replace('{date}', launchDate)}
                     </span>
                  ) : null}
                  {course.level && (
                     <span className="absolute bottom-3 left-3 rounded-full border border-white/20 bg-card/90 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-[oklch(0.22_0.04_255)] uppercase backdrop-blur-sm">
                        {course.level}
                     </span>
                  )}
               </div>
            </Link>

            {wishlists && (
               <TooltipProvider delayDuration={0}>
                  <Tooltip>
                     <TooltipTrigger asChild>
                        <Button
                           size="icon"
                           variant="ghost"
                           className="absolute top-3 right-3 z-10 h-8 w-8 rounded-full border border-white/30 bg-card/90 opacity-0 shadow-sm transition-opacity group-hover:opacity-100 hover:bg-card"
                           onClick={handleWishlist}
                        >
                           <Heart className={cn('h-4 w-4', isWishlisted ? 'fill-[color:var(--brand-red)] text-[color:var(--brand-red)]' : 'text-muted-foreground')} />
                        </Button>
                     </TooltipTrigger>
                     <TooltipContent>
                        <p>{isWishlisted ? frontend.remove_from_wishlist : frontend.add_to_wishlist}</p>
                     </TooltipContent>
                  </Tooltip>
               </TooltipProvider>
            )}
         </CardHeader>

         <div className={cn('flex flex-1 flex-col', viewType === 'list' && 'min-w-0')}>
            <CardContent className="flex flex-1 flex-col gap-3 p-5">
               <div className="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                  <span className="inline-flex items-center gap-1">
                     <Users className="h-3 w-3 text-primary" />
                     {course.enrollments_count || 0} {course.enrollments_count === 1 ? frontend.student : common.students}
                  </span>
                  <span className="inline-flex items-center gap-1">
                     <Clock className="h-3 w-3 text-[color:var(--brand-blue)]" />
                     {getCourseDuration(course, 'readable')}
                  </span>
               </div>

               <Link href={detailsUrl} className="space-y-2">
                  <h3 className="ssu-course-card__title">{course.title}</h3>

                  <p className="text-muted-foreground flex items-center gap-1.5 text-sm">
                     <Star className="h-3.5 w-3.5 fill-[color:var(--brand-blue)] text-[color:var(--brand-blue)]" />
                     <span className="font-medium text-foreground">{course.average_rating ? Number(course.average_rating).toFixed(1) : '0.0'}</span>
                     <span>
                        ({course.reviews_count || 0} {common.reviews})
                     </span>
                  </p>
               </Link>
            </CardContent>

            <CardFooter className="mt-auto flex flex-col items-stretch gap-2 border-t border-border/60 p-5 pt-4">
               <div className="flex items-center justify-between gap-3">
                  <div className="ssu-course-card__price capitalize">
                     {course.pricing_type === 'free' ? (
                        common.free
                     ) : isSubscription ? (
                        <>
                           <span>
                              {currency?.symbol}
                              {course.subscription_price ?? course.price}
                           </span>
                           <span className="text-muted-foreground ml-1 text-sm font-medium normal-case">/month</span>
                        </>
                     ) : course.discount ? (
                        <>
                           <span>
                              {currency?.symbol}
                              {course.discount_price}
                           </span>
                           <span className="text-muted-foreground ml-2 text-sm font-medium line-through">
                              {currency?.symbol}
                              {course.price}
                           </span>
                        </>
                     ) : (
                        <span>
                           {currency?.symbol}
                           {course.price}
                        </span>
                     )}
                  </div>

                  <Button asChild size="sm" className="rounded-full px-4">
                     <Link href={detailsUrl}>
                        {showPreviewCta ? (button.preview_course ?? 'Preview Course') : button.learn_more}
                        <ArrowRight className="ml-1 h-3.5 w-3.5" />
                     </Link>
                  </Button>
               </div>

               {isSubscription ? <SubscriptionBillingNotice course={course} variant="compact" /> : null}
            </CardFooter>
         </div>
      </article>
   );
};

export default CourseCard1;
