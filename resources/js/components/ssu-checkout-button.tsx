import CheckoutItem from '@/components/checkout-item';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { ReactNode } from 'react';

interface Props {
   item: 'exam' | 'course';
   item_id: number | string;
   children?: ReactNode;
   className?: string;
}

/**
 * SSU-styled checkout entry — links to PaymentGateways checkout with login redirect.
 */
const SsuCheckoutButton = ({ item, item_id, children, className }: Props) => {
   return (
      <CheckoutItem item={item} item_id={item_id}>
         <Button size="lg" className={cn('ssu-checkout-button w-full rounded-full', className)}>
            {children}
         </Button>
      </CheckoutItem>
   );
};

export default SsuCheckoutButton;
