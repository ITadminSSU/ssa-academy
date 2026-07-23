import { isSubscriptionCourse } from '@/lib/subscription-billing';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { Link, usePage } from '@inertiajs/react';
import { CreditCard, Info } from 'lucide-react';

interface Props {
   course?: Pick<Course, 'pricing_type' | 'billing_model'>;
   variant?: 'compact' | 'detail' | 'manage';
   className?: string;
   forceShow?: boolean;
}

const SubscriptionBillingNotice = ({ course, variant = 'detail', className, forceShow = false }: Props) => {
   const { translate } = usePage<SharedData>().props;
   const { frontend } = translate;

   if (!forceShow && course && !isSubscriptionCourse(course)) {
      return null;
   }

   if (variant === 'compact') {
      return (
         <p className={cn('text-muted-foreground text-xs', className)}>
            {frontend.subscription_monthly_cancel ?? 'Monthly · Cancel anytime'}
         </p>
      );
   }

   if (variant === 'manage') {
      return (
         <div className={cn('bg-muted/40 border-border/60 space-y-3 rounded-lg border p-4 text-sm', className)}>
            <div className="flex items-start gap-2">
               <CreditCard className="text-primary mt-0.5 h-4 w-4 shrink-0" />
               <div className="space-y-2">
                  <p className="font-medium">{frontend.subscription_cancel_title ?? 'How to cancel your subscription'}</p>
                  <ol className="text-muted-foreground list-decimal space-y-1.5 pl-4">
                     <li>{frontend.subscription_cancel_step_1 ?? 'Open Dashboard → My Subscriptions.'}</li>
                     <li>{frontend.subscription_cancel_step_2 ?? 'Click Manage billing.'}</li>
                     <li>{frontend.subscription_cancel_step_3 ?? 'Cancel your subscription in the secure billing portal.'}</li>
                  </ol>
                  <p className="text-muted-foreground text-xs leading-relaxed">
                     {frontend.subscription_cancel_self_service ??
                        'You must cancel yourself before your next billing date. We cannot cancel on your behalf by email or chat.'}
                  </p>
               </div>
            </div>
         </div>
      );
   }

   return (
      <div className={cn('bg-muted/40 border-border/60 space-y-2 rounded-lg border p-3 text-sm', className)}>
         <div className="flex items-start gap-2">
            <Info className="text-primary mt-0.5 h-4 w-4 shrink-0" />
            <div className="space-y-2">
               <p className="font-medium">{frontend.subscription_details_title ?? 'Subscription details'}</p>
               <ul className="text-muted-foreground list-disc space-y-1 pl-4 text-xs leading-relaxed">
                  <li>{frontend.subscription_billed_monthly ?? 'Billed monthly until you cancel.'}</li>
                  <li>
                     {frontend.subscription_cancel_anytime ??
                        'Cancel anytime from Dashboard → My Subscriptions → Manage billing.'}
                  </li>
                  <li>
                     {frontend.subscription_access_until_period_end ??
                        'After canceling, access continues until the end of your current billing period.'}
                  </li>
                  <li>
                     {frontend.subscription_self_service_cancel ??
                        'You are responsible for canceling before your next billing date.'}
                  </li>
               </ul>
               <p className="text-muted-foreground text-xs leading-relaxed">
                  {frontend.subscription_refund_note ?? 'Refunds follow our'}{' '}
                  <Link href={route('inner.page', { slug: 'refund-policy' })} className="text-primary font-medium underline-offset-2 hover:underline">
                     {frontend.refund_policy ?? 'Refund Policy'}
                  </Link>
                  .
               </p>
            </div>
         </div>
      </div>
   );
};

export default SubscriptionBillingNotice;
