import { Badge } from '@/components/ui/badge';

export type RefundStatus = 'paid' | 'refund_pending' | 'refunded';

const variantMap: Record<RefundStatus, 'default' | 'secondary' | 'outline' | 'destructive'> = {
   paid: 'default',
   refund_pending: 'outline',
   refunded: 'secondary',
};

const labelMap: Record<RefundStatus, string> = {
   paid: 'Paid',
   refund_pending: 'Refund Pending',
   refunded: 'Refunded',
};

const RefundStatusBadge = ({ status }: { status?: RefundStatus | string | null }) => {
   const value = (status ?? 'paid') as RefundStatus;

   return <Badge variant={variantMap[value] ?? 'secondary'}>{labelMap[value] ?? value}</Badge>;
};

export default RefundStatusBadge;
