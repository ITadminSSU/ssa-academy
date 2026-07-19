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
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import RefundStatusBadge from '@/pages/dashboard/payment-refunds/partials/refund-status-badge';
import { SharedData } from '@/types/global';
import { useForm, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { useState } from 'react';

interface RefundAttempt {
   id: number;
   gateway: string;
   transaction_id: string | null;
   success: boolean;
   gateway_refund_id: string | null;
   error_message: string | null;
   created_at: string;
   initiated_by?: { id: number; name: string; email: string };
   payment_history?: PaymentHistory;
}

interface Props {
   candidate: User;
   refundablePayments: PaymentHistory[];
   refundAttempts: RefundAttempt[];
}

const ProcessRefundSection = ({ candidate, refundablePayments, refundAttempts }: Props) => {
   const { translate } = usePage<SharedData>().props;
   const { dashboard, button } = translate;
   const isHired = candidate.candidate_status === 'hired';

   const [singlePayment, setSinglePayment] = useState<PaymentHistory | null>(null);
   const [processAllOpen, setProcessAllOpen] = useState(false);

   const singleForm = useForm({ confirmed: true as boolean, refund_notes: '' });
   const allForm = useForm({ confirmed: true as boolean, refund_notes: '' });

   const submitSingle = () => {
      if (!singlePayment) return;

      singleForm.post(route('candidates.refund.process', { candidate: candidate.id, payment: singlePayment.id }), {
         onSuccess: () => {
            setSinglePayment(null);
            singleForm.reset();
         },
      });
   };

   const submitAll = () => {
      allForm.post(route('candidates.refund.process-all', { candidate: candidate.id }), {
         onSuccess: () => {
            setProcessAllOpen(false);
            allForm.reset();
         },
      });
   };

   const totalRefundable = refundablePayments.reduce((sum, p) => sum + Number(p.amount), 0);

   if (!isHired) {
      return null;
   }

   return (
      <>
         <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-4">
               <CardTitle>{dashboard.process_refunds ?? 'Process Refunds'}</CardTitle>
               {refundablePayments.length > 0 && (
                  <Button variant="destructive" size="sm" onClick={() => setProcessAllOpen(true)}>
                     {dashboard.process_all_refunds ?? 'Process All Refunds'}
                  </Button>
               )}
            </CardHeader>
            <CardContent className="space-y-4">
               <p className="text-muted-foreground text-sm">
                  {dashboard.process_refunds_description ??
                     'This candidate is hired. Process Stripe/PayPal refunds using stored transaction IDs. This action is irreversible.'}
               </p>

               {refundablePayments.length === 0 ? (
                  <p className="text-muted-foreground text-sm">{dashboard.no_refundable_payments ?? 'No refundable Stripe/PayPal payments remaining.'}</p>
               ) : (
                  <Table>
                     <TableHeader>
                        <TableRow>
                           <TableHead>{dashboard.item ?? 'Item'}</TableHead>
                           <TableHead>{dashboard.amount ?? 'Amount'}</TableHead>
                           <TableHead>{dashboard.payment_method ?? 'Method'}</TableHead>
                           <TableHead>{dashboard.transaction_id ?? 'Transaction ID'}</TableHead>
                           <TableHead>{dashboard.refund_status ?? 'Status'}</TableHead>
                           <TableHead />
                        </TableRow>
                     </TableHeader>
                     <TableBody>
                        {refundablePayments.map((payment) => (
                           <TableRow key={payment.id}>
                              <TableCell>{payment.purchase?.title ?? 'N/A'}</TableCell>
                              <TableCell>${Number(payment.amount).toFixed(2)}</TableCell>
                              <TableCell className="capitalize">{payment.payment_type}</TableCell>
                              <TableCell className="max-w-[160px] truncate font-mono text-xs">{payment.transaction_id}</TableCell>
                              <TableCell>
                                 <RefundStatusBadge status={payment.refund_status} />
                              </TableCell>
                              <TableCell>
                                 <Button variant="outline" size="sm" onClick={() => setSinglePayment(payment)}>
                                    {dashboard.process_refund ?? 'Process Refund'}
                                 </Button>
                              </TableCell>
                           </TableRow>
                        ))}
                     </TableBody>
                  </Table>
               )}
            </CardContent>
         </Card>

         {refundAttempts.length > 0 && (
            <Card>
               <CardHeader>
                  <CardTitle>{dashboard.refund_attempt_log ?? 'Refund Attempt Log'}</CardTitle>
               </CardHeader>
               <CardContent>
                  <Table>
                     <TableHeader>
                        <TableRow>
                           <TableHead>{dashboard.payment ?? 'Payment'}</TableHead>
                           <TableHead>{dashboard.gateway ?? 'Gateway'}</TableHead>
                           <TableHead>{dashboard.result ?? 'Result'}</TableHead>
                           <TableHead>{dashboard.processed_by ?? 'Processed By'}</TableHead>
                           <TableHead>Date</TableHead>
                        </TableRow>
                     </TableHeader>
                     <TableBody>
                        {refundAttempts.map((attempt) => (
                           <TableRow key={attempt.id}>
                              <TableCell>#{attempt.payment_history?.id ?? '—'}</TableCell>
                              <TableCell className="capitalize">{attempt.gateway}</TableCell>
                              <TableCell>
                                 {attempt.success ? (
                                    <Badge variant="default">{dashboard.success ?? 'Success'}</Badge>
                                 ) : (
                                    <span className="text-destructive text-sm">{attempt.error_message ?? dashboard.failed ?? 'Failed'}</span>
                                 )}
                              </TableCell>
                              <TableCell className="text-sm">{attempt.initiated_by?.name ?? '—'}</TableCell>
                              <TableCell className="text-sm">{format(new Date(attempt.created_at), 'MMM dd, yyyy HH:mm')}</TableCell>
                           </TableRow>
                        ))}
                     </TableBody>
                  </Table>
               </CardContent>
            </Card>
         )}

         <AlertDialog open={!!singlePayment} onOpenChange={(open) => !open && setSinglePayment(null)}>
            <AlertDialogContent>
               <AlertDialogHeader>
                  <AlertDialogTitle>{dashboard.confirm_refund_title ?? 'Confirm Gateway Refund'}</AlertDialogTitle>
                  <AlertDialogDescription asChild>
                     <div className="space-y-2 text-sm">
                        <p>{dashboard.confirm_refund_description ?? 'This will call the payment gateway API and cannot be undone.'}</p>
                        {singlePayment && (
                           <ul className="bg-muted/40 list-inside list-disc rounded-md p-3">
                              <li>
                                 {singlePayment.purchase?.title ?? 'Item'} — ${Number(singlePayment.amount).toFixed(2)}
                              </li>
                              <li className="capitalize">{singlePayment.payment_type}</li>
                              <li className="font-mono text-xs">{singlePayment.transaction_id}</li>
                           </ul>
                        )}
                     </div>
                  </AlertDialogDescription>
               </AlertDialogHeader>
               <AlertDialogFooter>
                  <AlertDialogCancel>{button.cancel ?? 'Cancel'}</AlertDialogCancel>
                  <AlertDialogAction asChild>
                     <LoadingButton loading={singleForm.processing} variant="destructive" onClick={submitSingle}>
                        {dashboard.confirm_process_refund ?? 'Yes, Process Refund'}
                     </LoadingButton>
                  </AlertDialogAction>
               </AlertDialogFooter>
            </AlertDialogContent>
         </AlertDialog>

         <AlertDialog open={processAllOpen} onOpenChange={setProcessAllOpen}>
            <AlertDialogContent>
               <AlertDialogHeader>
                  <AlertDialogTitle>{dashboard.confirm_all_refunds_title ?? 'Confirm All Refunds'}</AlertDialogTitle>
                  <AlertDialogDescription asChild>
                     <div className="space-y-2 text-sm">
                        <p>
                           {dashboard.confirm_all_refunds_description ??
                              'This will process refunds for all eligible payments via Stripe/PayPal.'}
                        </p>
                        <p className="font-medium">
                           {refundablePayments.length} payment(s) — ${totalRefundable.toFixed(2)} total
                        </p>
                     </div>
                  </AlertDialogDescription>
               </AlertDialogHeader>
               <AlertDialogFooter>
                  <AlertDialogCancel>{button.cancel ?? 'Cancel'}</AlertDialogCancel>
                  <AlertDialogAction asChild>
                     <LoadingButton loading={allForm.processing} variant="destructive" onClick={submitAll}>
                        {dashboard.confirm_process_all_refunds ?? 'Yes, Process All'}
                     </LoadingButton>
                  </AlertDialogAction>
               </AlertDialogFooter>
            </AlertDialogContent>
         </AlertDialog>
      </>
   );
};

export default ProcessRefundSection;
