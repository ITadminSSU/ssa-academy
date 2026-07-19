import LoadingButton from '@/components/loading-button';
import {
   AlertDialog,
   AlertDialogAction,
   AlertDialogCancel,
   AlertDialogContent,
   AlertDialogDescription,
   AlertDialogFooter,
   AlertDialogHeader,
   AlertDialogTitle,
   AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';

interface Props {
   payment: PaymentHistory & { can_gateway_refund?: boolean };
   label?: string;
}

const ProcessRefundButton = ({ payment, label = '1-Click Refund' }: Props) => {
   const form = useForm({ confirmed: true as boolean, refund_notes: '' });

   if (!payment.can_gateway_refund) {
      return null;
   }

   const submit = () => {
      form.post(route('payment-refunds.process', payment.id));
   };

   return (
      <AlertDialog>
         <AlertDialogTrigger asChild>
            <Button variant="destructive" size="sm" className="gap-1">
               <RotateCcw className="h-3.5 w-3.5" />
               {label}
            </Button>
         </AlertDialogTrigger>
         <AlertDialogContent>
            <AlertDialogHeader>
               <AlertDialogTitle>Process gateway refund?</AlertDialogTitle>
               <AlertDialogDescription>
                  This will immediately refund ${Number(payment.amount).toFixed(2)} via {payment.payment_type} for {payment.user?.name}. This
                  action cannot be undone.
               </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
               <AlertDialogCancel>Cancel</AlertDialogCancel>
               <AlertDialogAction asChild>
                  <LoadingButton loading={form.processing} onClick={submit}>
                     Confirm Refund
                  </LoadingButton>
               </AlertDialogAction>
            </AlertDialogFooter>
         </AlertDialogContent>
      </AlertDialog>
   );
};

export default ProcessRefundButton;
