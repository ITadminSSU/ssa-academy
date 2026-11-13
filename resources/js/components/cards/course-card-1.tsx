import { CoursePriceAmount, CoursePriceNotes } from '@/components/cards/course-price';
import { Button } from '@/components/ui/button';
import { CardContent, CardFooter, CardHeader } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { formatCourseLaunchDate, formatLaunchCountdownShort, isCourseComingSoon } from '@/lib/course-launch';
import { formatOfferAmount } from '@/lib/launch-offer';
import { isSubscriptionCourse } from '@/lib/subscription-billing';
import { cn, getCourseDuration, systemCurrency } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, Clock, Heart, Star, Users } from 'lucide-react';

const paidAmount = (value: number | string | null | undefined): number | null => {
   const amount = Number(value);

   return Number.isFinite(amount) ? amount : null;
};

const catalogChoicePrices = (course: Course, symbol: string): { once: string; recurring: string; list: string } | null => {
   if (course.pricing_type !== 'paid') {
      return null;
   }

   if (course.billing_model !== 'one_time_or_monthly' && course.billing_model !== 'one_time_or_installment') {
      return null;
   }

   const onceAmount = paidAmount(course.price);

   if (onceAmount === null) {
      return null;
   }

   const once = `${symbol}${formatOfferAmount(onceAmount)}`;

   if (course.billing_model === 'one_time_or_installment') {
      const installment = paidAmount(course.installment_price);

      if (installment === null) {
         return null;
      }

      const months = Math.trunc(Number(course.installment_months));
      const recurring =
         Number.isInteger(months) && months > 0
            ? `${symbol}${formatOfferAmount(installment)} × ${months}`
            : `${symbol}${formatOfferAmount(installment)}`;

      return { once, recurring, list: `${once} once · ${recurring}` };
   }

   const monthly = paidAmount(course.subscription_price);

   if (monthly === null) {
      return null;
   }

   const recurring = `${symbol}${formatOfferAmount(monthly)}/mo`;

   return { once, recurring, list: `${once} once · ${recurring}` };
};

interface Props {
   course: Course;
   viewType?: 'grid' | 'list';
   variant?: 'default' | 'catalog';
   className?: string;
   wishlists?: CourseWishlist[];
}

const CourseCard1 = ({ course, viewType = 'grid', variant = 'default', className, wishlists }: Props) => {
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
   const symbol = currency?.symbol ?? '$';
   const choicePrices = catalogChoicePrices(course, symbol);
   const duration = getCourseDuration(course, 'readable');
   const reviewCount = Number(course.reviews_count || 0);
   const outcome = (course.short_description || '')
      .replace(/<[^>]+>/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
   const viewLabel = showPreviewCta ? 'Preview course' : 'View course';

   const handleWishlist = () => {
      if (isWishlisted) {
         router.delete(route('course-wishlists.destroy', { id: isWishlisted.id }));
      } else {
         router.post(route('course-wishlists.store', { user_id: user?.id, course_id: course.id }));
      }
   };

   const poster = (frameClass: string) => (
      <Link href={detailsUrl} className={cn('relative block overflow-hidden', frameClass)}>
         <img
            src={course.thumbnail || '/assets/images/blank-image.jpg'}
            alt={course.title}
            className="h-full w-full object-cover"
            onError={(e) => {
               const target = e.target as HTMLImageElement;
               target.src = '/assets/images/blank-image.jpg';
            }}
         />
         <span className="absolute top-2 right-2 z-[1] flex flex-col items-end gap-1">
            {comingSoon ? (
               <span className="rounded-full bg-amber-400 px-2 py-0.5 text-[10px] font-bold tracking-wide text-amber-950 uppercase">
                  {frontend.coming_soon ?? 'Coming Soon'}
               </span>
            ) : null}
            {isSubscription && !comingSoon ? (
               <span className="text-primary bg-card/95 rounded-full border border-white/20 px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase">
                  {frontend.subscription_monthly_label ?? 'Monthly'}
               </span>
            ) : null}
         </span>
         {comingSoon && launchDate ? (
            <span className="absolute bottom-2 left-2 z-[1] rounded-full border border-amber-200/80 bg-amber-50/95 px-2 py-0.5 text-[10px] font-semibold text-amber-900">
               {countdownShort ?? (frontend.launches_on ?? 'Launches {date}').replace('{date}', launchDate)}
            </span>
         ) : null}
      </Link>
   );

   const wishlist = wishlists ? (
      <TooltipProvider delayDuration={0}>
         <Tooltip>
            <TooltipTrigger asChild>
               <Button
                  size="icon"
                  variant="ghost"
                  className="bg-card/90 hover:bg-card absolute top-2 left-2 z-10 h-8 w-8 rounded-full border border-white/40 shadow-sm"
                  onClick={handleWishlist}
               >
                  <Heart
                     className={cn('h-4 w-4', isWishlisted ? 'fill-[color:var(--brand-red)] text-[color:var(--brand-red)]' : 'text-muted-foreground')}
                  />
               </Button>
            </TooltipTrigger>
            <TooltipContent>
               <p>{isWishlisted ? frontend.remove_from_wishlist : frontend.add_to_wishlist}</p>
            </TooltipContent>
         </Tooltip>
      </TooltipProvider>
   ) : null;

   const rating =
      reviewCount > 0 ? (
         <p className="text-muted-foreground flex items-center gap-1.5 text-xs">
            <Star className="h-3.5 w-3.5 fill-[color:var(--brand-gold)] text-[color:var(--brand-gold)]" />
            <span className="text-foreground font-medium">{Number(course.average_rating).toFixed(1)}</span>
            <span>
               ({reviewCount} {common.reviews})
            </span>
         </p>
      ) : null;

   const viewCourse = (
      <Button
         asChild
         size="sm"
         className="bg-accent text-accent-foreground h-7 shrink-0 rounded-full px-3 text-xs hover:bg-[color:var(--accent-hover)]"
      >
         <Link href={detailsUrl}>
            {viewLabel}
            <ArrowRight className="ml-1 h-3 w-3" />
         </Link>
      </Button>
   );

   if (variant === 'catalog' && viewType === 'list') {
      return (
         <article className={cn('border-border/70 bg-card flex gap-4 rounded-xl border p-3', className)}>
            <div className="relative h-24 w-36 shrink-0">
               {poster('h-full w-full rounded-lg')}
               {wishlist}
            </div>
            <div className="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-center">
               <div className="min-w-0 flex-1 space-y-1">
                  <Link href={detailsUrl}>
                     <h3 className="font-display text-primary text-base font-bold">{course.title}</h3>
                  </Link>
                  {outcome ? <p className="text-muted-foreground line-clamp-1 text-sm">{outcome}</p> : null}
                  {rating}
                  <p className="text-primary flex flex-wrap gap-x-3 text-xs font-medium">
                     {course.level ? <span className="tracking-wide uppercase">{course.level}</span> : null}
                     <span>{duration}</span>
                  </p>
               </div>
               <div className="flex shrink-0 flex-col items-start gap-2 sm:max-w-xs sm:items-end">
                  {choicePrices ? (
                     <p className="text-primary text-sm font-semibold">{choicePrices.list}</p>
                  ) : (
                     <CoursePriceAmount course={course} symbol={symbol} freeLabel={common.free} />
                  )}
                  <CoursePriceNotes course={course} symbol={symbol} />
                  {viewCourse}
               </div>
            </div>
         </article>
      );
   }

   if (variant === 'catalog') {
      return (
         <article className={cn('border-border/70 bg-card overflow-hidden rounded-xl border', className)}>
            <div className="relative">
               {poster('aspect-[16/10]')}
               {wishlist}
            </div>
            {rating ? <div className="px-3 pt-2">{rating}</div> : null}
            <div className="bg-primary text-primary-foreground flex flex-nowrap items-center gap-x-3 px-3 py-2 text-xs">
               {course.level ? <span className="shrink-0 font-semibold tracking-wide whitespace-nowrap uppercase">{course.level}</span> : null}
               <span className="shrink-0 whitespace-nowrap">{duration}</span>
               <div className="ml-auto grid shrink-0 grid-rows-2 justify-items-end text-right leading-tight">
                  <div className="min-h-[1.15em]">
                     {choicePrices ? (
                        <span className="font-semibold">{choicePrices.once}</span>
                     ) : (
                        <CoursePriceAmount course={course} symbol={symbol} freeLabel={common.free} tone="on-navy" />
                     )}
                  </div>
                  <div className="min-h-[1.15em]">{choicePrices ? choicePrices.recurring : null}</div>
               </div>
               {viewCourse}
            </div>
            <div className="px-3 py-2 empty:hidden">
               <CoursePriceNotes course={course} symbol={symbol} />
            </div>
         </article>
      );
   }

   return (
      <article className={cn('ssu-course-card group flex flex-col', viewType === 'list' && 'sm:flex-row sm:items-stretch', className)}>
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
                  <div className="from-primary/50 pointer-events-none absolute inset-0 bg-gradient-to-t via-transparent to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100" />

                  {comingSoon ? (
                     <span className="absolute top-3 right-3 z-[1] rounded-full bg-amber-400 px-2.5 py-0.5 text-[10px] font-bold tracking-wide text-amber-950 uppercase shadow-sm">
                        {frontend.coming_soon ?? 'Coming Soon'}
                     </span>
                  ) : null}

                  {course.pricing_type === 'free' && <span className="ssu-course-card__badge">{common.free}</span>}
                  {isSubscription && !comingSoon ? (
                     <span className="text-primary bg-card/95 absolute top-3 right-3 z-[1] rounded-full border border-white/20 px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase backdrop-blur-sm">
                        {frontend.subscription_monthly_label ?? 'Monthly'}
                     </span>
                  ) : null}
                  {comingSoon && launchDate ? (
                     <span className="absolute right-3 bottom-3 z-[1] rounded-full border border-amber-200/80 bg-amber-50/95 px-2 py-0.5 text-[10px] font-semibold text-amber-900 backdrop-blur-sm">
                        {countdownShort ?? (frontend.launches_on ?? 'Launches {date}').replace('{date}', launchDate)}
                     </span>
                  ) : null}
                  {course.level && (
                     <span className="text-primary bg-card/90 absolute bottom-3 left-3 rounded-full border border-white/20 px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase backdrop-blur-sm">
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
                           className="bg-card/90 hover:bg-card absolute top-3 right-3 z-10 h-8 w-8 rounded-full border border-white/30 opacity-0 shadow-sm transition-opacity group-hover:opacity-100"
                           onClick={handleWishlist}
                        >
                           <Heart
                              className={cn(
                                 'h-4 w-4',
                                 isWishlisted ? 'fill-[color:var(--brand-red)] text-[color:var(--brand-red)]' : 'text-muted-foreground',
                              )}
                           />
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
                     <Users className="text-primary h-3 w-3" />
                     {course.enrollments_count || 0} {course.enrollments_count === 1 ? frontend.student : common.students}
                  </span>
                  <span className="inline-flex items-center gap-1">
                     <Clock className="h-3 w-3 text-[color:var(--brand-blue)]" />
                     {duration}
                  </span>
               </div>

               <Link href={detailsUrl} className="space-y-2">
                  <h3 className="ssu-course-card__title">{course.title}</h3>

                  <p className="text-muted-foreground flex items-center gap-1.5 text-sm">
                     <Star className="h-3.5 w-3.5 fill-[color:var(--brand-blue)] text-[color:var(--brand-blue)]" />
                     <span className="text-foreground font-medium">{course.average_rating ? Number(course.average_rating).toFixed(1) : '0.0'}</span>
                     <span>
                        ({course.reviews_count || 0} {common.reviews})
                     </span>
                  </p>
               </Link>
            </CardContent>

            <CardFooter className="border-border/60 mt-auto flex flex-col items-stretch gap-2 border-t p-5 pt-4">
               <div className="flex items-center justify-between gap-x-3">
                  <div className="ssu-course-card__price min-w-0">
                     <CoursePriceAmount course={course} symbol={symbol} freeLabel={common.free} />
                  </div>

                  <Button asChild size="sm" className="ml-auto h-7 shrink-0 rounded-full px-3 text-xs">
                     <Link href={detailsUrl}>
                        {showPreviewCta ? (button.preview_course ?? 'Preview Course') : button.learn_more}
                        <ArrowRight className="ml-1 h-3 w-3" />
                     </Link>
                  </Button>
               </div>

               <CoursePriceNotes course={course} symbol={symbol} />
            </CardFooter>
         </div>
      </article>
   );
};

export default CourseCard1;
