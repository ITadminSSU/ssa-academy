import SubscriptionBillingNotice from '@/components/subscription-billing-notice';
import { formatOfferAmount, getLaunchOfferView } from '@/lib/launch-offer';
import { installmentPlanLabel, isSubscriptionCourse, isUpfrontSubscriptionCourse } from '@/lib/subscription-billing';
import { cn } from '@/lib/utils';

interface CoursePriceProps {
   course: Course;
   symbol: string;
   freeLabel: string;
   tone?: 'default' | 'on-navy';
}

export const CoursePriceAmount = ({ course, symbol, freeLabel, tone = 'default' }: CoursePriceProps) => {
   const isSubscription = isSubscriptionCourse(course);
   const isUpfrontSubscription = isUpfrontSubscriptionCourse(course);
   const launchOffer = getLaunchOfferView(course);
   const isLaunchPreRegister = launchOffer.enabled && launchOffer.phase === 'pre_register';
   const isLaunchFullPrice = launchOffer.enabled && launchOffer.phase === 'full_price';
   const catalogPromo = launchOffer.catalogPromo;
   const installmentLabel = installmentPlanLabel(course, symbol);
   const muted = tone === 'on-navy' ? 'text-primary-foreground/75' : 'text-muted-foreground';

   return (
      <div
         className={cn(
            'flex min-w-0 gap-1.5',
            catalogPromo ? 'items-start whitespace-normal' : 'items-baseline',
            tone === 'on-navy' && 'text-primary-foreground justify-end text-right',
         )}
      >
         {course.pricing_type === 'free' ? (
            freeLabel
         ) : isLaunchPreRegister ? (
            catalogPromo ? (
               <div className="flex min-w-0 flex-col gap-0.5 normal-case">
                  <span className={cn(muted, 'text-sm font-medium line-through')}>
                     {symbol}
                     {formatOfferAmount(catalogPromo.list_price)} course price
                  </span>
                  <span>
                     {symbol}
                     {formatOfferAmount(catalogPromo.total_with_coupon)} <span className={cn(muted, 'text-xs font-medium')}>with coupon</span>
                  </span>
               </div>
            ) : (
               <span className="normal-case">
                  {symbol}
                  {formatOfferAmount(launchOffer.listPrice)} <span className={cn(muted, 'text-xs font-medium')}>course price</span>
               </span>
            )
         ) : isLaunchFullPrice ? (
            catalogPromo ? (
               <div className="flex min-w-0 flex-col gap-0.5 normal-case">
                  <span className={cn(muted, 'text-sm font-medium line-through')}>
                     {symbol}
                     {formatOfferAmount(catalogPromo.full_upfront_price)}
                  </span>
                  <span>
                     {symbol}
                     {formatOfferAmount(catalogPromo.full_upfront_with_coupon)} <span className={cn(muted, 'text-xs font-medium')}>with coupon</span>
                  </span>
               </div>
            ) : (
               <>
                  <span>
                     {symbol}
                     {formatOfferAmount(launchOffer.fullUpfrontPrice)}
                  </span>
                  <span className={cn(muted, 'ml-1 text-sm font-medium normal-case')}>
                     +{symbol}
                     {formatOfferAmount(launchOffer.subscriptionPrice)}/mo
                  </span>
               </>
            )
         ) : isUpfrontSubscription ? (
            catalogPromo?.kind === 'upfront' ? (
               <div className="flex min-w-0 flex-col gap-0.5 normal-case">
                  <span className={cn(muted, 'text-sm font-medium')}>
                     <span className="line-through">
                        {symbol}
                        {formatOfferAmount(catalogPromo.list_price)}
                     </span>{' '}
                     course price
                  </span>
                  <span>
                     {symbol}
                     {formatOfferAmount(catalogPromo.total_with_coupon)} <span className={cn(muted, 'text-xs font-medium')}>with coupon</span>
                     <span className={cn(muted, 'ml-1 text-sm font-medium')}>
                        +{symbol}
                        {formatOfferAmount(catalogPromo.subscription_price)}/mo
                     </span>
                  </span>
               </div>
            ) : (
               <>
                  <span>
                     {symbol}
                     {course.price}
                  </span>
                  <span className={cn(muted, 'ml-1 text-sm font-medium normal-case')}>
                     +{symbol}
                     {course.subscription_price}/mo
                  </span>
               </>
            )
         ) : catalogPromo?.kind === 'one_time' ? (
            <div className="flex min-w-0 flex-col gap-0.5 normal-case">
               <span className={cn(muted, 'text-sm font-medium line-through')}>
                  {symbol}
                  {formatOfferAmount(catalogPromo.list_price)} course price
               </span>
               <span>
                  {symbol}
                  {formatOfferAmount(catalogPromo.total_with_coupon)} <span className={cn(muted, 'text-xs font-medium')}>with coupon</span>
               </span>
            </div>
         ) : installmentLabel ? (
            <span className="normal-case">{installmentLabel}</span>
         ) : isSubscription ? (
            <>
               <span>
                  {symbol}
                  {course.launch_offer_enabled ? (course.launch_full_upfront_price ?? 75) : (course.subscription_price ?? course.price)}
               </span>
               {course.launch_offer_enabled ? (
                  <span className={cn(muted, 'ml-1 text-sm font-medium normal-case')}>
                     +{symbol}
                     {course.subscription_price}/mo
                  </span>
               ) : (
                  <span className={cn(muted, 'ml-1 text-sm font-medium normal-case')}>/month</span>
               )}
            </>
         ) : course.discount ? (
            <>
               <span>
                  {symbol}
                  {course.discount_price}
               </span>
               <span className={cn(muted, 'ml-2 text-sm font-medium line-through')}>
                  {symbol}
                  {course.price}
               </span>
            </>
         ) : (
            <span>
               {symbol}
               {course.price}
            </span>
         )}
      </div>
   );
};

export const CoursePriceNotes = ({ course, symbol }: Pick<CoursePriceProps, 'course' | 'symbol'>) => {
   const isSubscription = isSubscriptionCourse(course);
   const launchOffer = getLaunchOfferView(course);
   const isLaunchPreRegister = launchOffer.enabled && launchOffer.phase === 'pre_register';
   const isLaunchFullPrice = launchOffer.enabled && launchOffer.phase === 'full_price';
   const catalogPromo = launchOffer.catalogPromo;

   if (isLaunchPreRegister) {
      return (
         <div className="space-y-1">
            {catalogPromo ? <p className="text-muted-foreground text-xs leading-snug normal-case">Enter code at checkout</p> : null}
            <p className="text-muted-foreground text-xs leading-snug normal-case">
               {symbol}
               {formatOfferAmount(catalogPromo?.deposit_amount ?? launchOffer.depositAmount)} to pre-register
               {' · '}
               {symbol}
               {formatOfferAmount(catalogPromo?.balance_amount ?? launchOffer.balanceAmount)} at launch day
               {catalogPromo ? (
                  <>
                     {' '}
                     or {symbol}
                     {formatOfferAmount(catalogPromo.balance_with_coupon)} using coupons
                  </>
               ) : null}
               {' · '}
               {symbol}
               {formatOfferAmount(catalogPromo?.subscription_price ?? launchOffer.subscriptionPrice)} monthly subscription
            </p>
         </div>
      );
   }

   if (isLaunchFullPrice) {
      return (
         <p className="text-muted-foreground text-xs leading-snug normal-case">
            {catalogPromo ? (
               <>
                  Pay {symbol}
                  {formatOfferAmount(catalogPromo.full_upfront_price)} or {symbol}
                  {formatOfferAmount(catalogPromo.full_upfront_with_coupon)} using coupons · then {symbol}
                  {formatOfferAmount(catalogPromo.subscription_price)}/mo
               </>
            ) : (
               <>
                  Pay {symbol}
                  {formatOfferAmount(launchOffer.fullUpfrontPrice)} for full access · then {symbol}
                  {formatOfferAmount(launchOffer.subscriptionPrice)}/mo
               </>
            )}
         </p>
      );
   }

   if (catalogPromo?.kind === 'one_time' || catalogPromo?.kind === 'upfront' || isSubscription) {
      return (
         <div className="space-y-1">
            {catalogPromo?.kind === 'one_time' || catalogPromo?.kind === 'upfront' ? (
               <p className="text-muted-foreground text-xs leading-snug normal-case">
                  {catalogPromo.kind === 'upfront' ? 'Enter code at checkout · enrollment only' : 'Enter code at checkout'}
               </p>
            ) : null}
            {isSubscription ? <SubscriptionBillingNotice course={course} variant="compact" /> : null}
         </div>
      );
   }

   return null;
};
