import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { SharedData } from '@/types/global';
import { useForm, usePage } from '@inertiajs/react';
import { ReactNode, useState } from 'react';
import { RefundStatus } from './refund-status-badge';

interface StatusOption {
   value: string;
   label: string;
}

interface AuditLog {
   id: number;
   previous_status: string | null;
   new_status: string;
   previous_notes: string | null;
   new_notes: string | null;
   created_at: string;
   changed_by?: { id: number; name: string; email: string };
}

interface Props {
   payment: PaymentHistory;
   statuses: StatusOption[];
   auditLogs?: AuditLog[];
   actionComponent: ReactNode;
}

const RefundUpdateModal = ({ payment, statuses, auditLogs = [], actionComponent }: Props) => {
   const [open, setOpen] = useState(false);
   const { translate } = usePage<SharedData>().props;
   const { dashboard, button } = translate;

   const { data, setData, put, processing, errors, reset } = useForm({
      refund_status: (payment.refund_status ?? 'paid') as RefundStatus,
      refund_notes: payment.refund_notes ?? '',
   });

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();

      put(route('payment-refunds.update', { payment: payment.id }), {
         onSuccess: () => {
            reset();
            setOpen(false);
         },
      });
   };

   return (
      <Dialog open={open} onOpenChange={setOpen}>
         <DialogTrigger asChild>{actionComponent}</DialogTrigger>
         <DialogContent className="max-w-lg">
            <DialogHeader>
               <DialogTitle>
                  {dashboard.refund_tracking ?? 'Refund Tracking'} — #{payment.id}
               </DialogTitle>
            </DialogHeader>

            <form onSubmit={handleSubmit} className="space-y-4">
               <div>
                  <Label>{dashboard.refund_status ?? 'Refund Status'} *</Label>
                  <Select value={data.refund_status} onValueChange={(value) => setData('refund_status', value as RefundStatus)}>
                     <SelectTrigger>
                        <SelectValue placeholder="Select status" />
                     </SelectTrigger>
                     <SelectContent>
                        {statuses.map((status) => (
                           <SelectItem key={status.value} value={status.value}>
                              {status.label}
                           </SelectItem>
                        ))}
                     </SelectContent>
                  </Select>
                  <InputError message={errors.refund_status} />
               </div>

               <div>
                  <Label>{dashboard.refund_notes ?? 'HR Notes'}</Label>
                  <Textarea
                     rows={4}
                     value={data.refund_notes}
                     onChange={(e) => setData('refund_notes', e.target.value)}
                     placeholder={dashboard.refund_notes_placeholder ?? 'Internal notes for HR (manual tracking only)'}
                  />
                  <InputError message={errors.refund_notes} />
               </div>

               <LoadingButton loading={processing}>{button.save_changes ?? 'Save Changes'}</LoadingButton>
            </form>

            {auditLogs.length > 0 && (
               <div className="border-border mt-4 border-t pt-4">
                  <p className="mb-3 text-sm font-medium">{dashboard.refund_audit_log ?? 'Audit Log'}</p>
                  <ul className="max-h-48 space-y-3 overflow-y-auto text-sm">
                     {auditLogs.map((log) => (
                        <li key={log.id} className="bg-muted/40 rounded-md p-3">
                           <p className="font-medium">
                              {(log.previous_status ?? '—').replace('_', ' ')} → {log.new_status.replace('_', ' ')}
                           </p>
                           <p className="text-muted-foreground text-xs">
                              {log.changed_by?.name ?? 'Admin'} · {new Date(log.created_at).toLocaleString()}
                           </p>
                           {(log.new_notes || log.previous_notes) && (
                              <p className="text-muted-foreground mt-1 text-xs whitespace-pre-wrap">{log.new_notes ?? log.previous_notes}</p>
                           )}
                        </li>
                     ))}
                  </ul>
               </div>
            )}
         </DialogContent>
      </Dialog>
   );
};

export default RefundUpdateModal;
