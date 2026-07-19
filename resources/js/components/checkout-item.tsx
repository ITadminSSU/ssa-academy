import { SharedData } from '@/types/global';
import { usePage } from '@inertiajs/react';

interface Props {
   from?: 'api' | 'web';
   item: 'exam' | 'course';
   item_id: number | string;
   children: React.ReactNode;
}

const CheckoutItem = ({ from = 'web', item, item_id, children }: Props) => {
   const { auth } = usePage<SharedData>().props;
   const checkoutUrl = route('payments.index', { from, item, id: item_id });
   const href = auth.user
      ? checkoutUrl
      : `${route('login')}?redirect=${encodeURIComponent(checkoutUrl)}`;

   return <a href={href}>{children}</a>;
};

export default CheckoutItem;
