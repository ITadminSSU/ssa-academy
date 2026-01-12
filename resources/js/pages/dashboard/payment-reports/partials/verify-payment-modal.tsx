import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { format } from 'date-fns';
import { CheckCircle2, Download, FileText, Info, XCircle } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { Renderer } from 'richtor';
import 'richtor/styles';

interface Props {
   payment: PaymentHistory;
   isOpen: boolean;
   onClose: () => void;
}

const VerifyPaymentModal = ({ payment, isOpen, onClose }: Props) => {
   const paymentMeta = payment.meta ?? {};
   const status = (paymentMeta.status as string) || 'pending';
   const paymentInfo = paymentMeta.payment_info as string | undefined;
   const [adminNotes, setAdminNotes] = useState('');

   const handleVerify = (e: FormEvent) => {
      router.post(
         route('payment-reports.offline.verify', payment.id),
         {
            admin_notes: adminNotes,
         },
         {
            onSuccess: () => {
               onClose();
            },
         },
      );
   };

   const handleReject = (e: FormEvent) => {
      router.post(
         route('payment-reports.offline.reject', payment.id),
         {
            admin_notes: adminNotes,
         },
         {
            onSuccess: () => {
               onClose();
            },
         },
      );
   };

   return (
      <Dialog open={isOpen} onOpenChange={onClose}>
         <DialogContent className="max-h-[90vh] max-w-3xl overflow-y-auto">
            <DialogHeader>
               <DialogTitle className="flex items-center gap-2">
                  <FileText className="h-5 w-5" />
                  Offline Payment Details
               </DialogTitle>
               <DialogDescription>Review and verify the offline payment submission</DialogDescription>
            </DialogHeader>

            <div className="space-y-6">
               {/* Status Badge */}
               <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">Payment Status:</span>
                  <Badge variant={status === 'verified' ? 'default' : status === 'rejected' ? 'destructive' : 'secondary'} className="gap-1">
                     {status === 'verified' ? (
                        <CheckCircle2 className="h-3 w-3" />
                     ) : status === 'rejected' ? (
                        <XCircle className="h-3 w-3" />
                     ) : (
                        <Info className="h-3 w-3" />
                     )}
                     {status as string}
                  </Badge>
               </div>

               {/* Payment Information */}
               <div className="bg-muted/50 space-y-3 rounded-lg border p-4">
                  <h3 className="text-sm font-semibold">Payment Information</h3>
                  <div className="grid grid-cols-2 gap-4 text-sm">
                     <div>
                        <span className="text-muted-foreground">Transaction ID:</span>
                        <p className="font-mono font-medium">{payment.transaction_id}</p>
                     </div>
                     <div>
                        <span className="text-muted-foreground">Amount:</span>
                        <p className="text-lg font-medium">${Number(payment.amount).toFixed(2)}</p>
                     </div>
                     <div>
                        <span className="text-muted-foreground">Payment Date:</span>
                        <p className="font-medium">
                           {paymentMeta.payment_date ? format(new Date(paymentMeta.payment_date as string), 'MMM dd, yyyy') : 'N/A'}
                        </p>
                     </div>
                     <div>
                        <span className="text-muted-foreground">Submitted At:</span>
                        <p className="font-medium">{format(new Date(payment.created_at as string), 'MMM dd, yyyy HH:mm')}</p>
                     </div>
                  </div>
               </div>

               {/* Payment Info (Rich Text) */}
               {!!paymentInfo && (
                  <div className="bg-muted/50 space-y-3 rounded-lg border p-4">
                     <h3 className="text-sm font-semibold">Submitted Payment Details</h3>
                     <Renderer value={paymentInfo} />
                  </div>
               )}

               {/* Attachment */}
               {payment.media && payment.media.length > 0 && (
                  <div className="bg-muted/50 space-y-3 rounded-lg border p-4">
                     <h3 className="text-sm font-semibold">Attachment</h3>
                     <div className="flex items-center gap-3">
                        <FileText className="text-muted-foreground h-8 w-8" />
                        <div className="flex-1">
                           <p className="text-sm font-medium">{payment.media[0].file_name}</p>
                           <p className="text-muted-foreground text-xs">Transaction proof</p>
                        </div>
                        <Button size="sm" variant="outline" asChild>
                           <a href={payment.media[0].original_url} target="_blank" rel="noopener noreferrer">
                              <Download className="mr-1 h-4 w-4" />
                              Download
                           </a>
                        </Button>
                     </div>
                  </div>
               )}

               {/* Verification Form (Only for Pending) */}
               {(status === 'pending' || status === 'rejected') && (
                  <>
                     <Alert>
                        <Info className="h-4 w-4" />
                        <AlertDescription>Verifying this payment will automatically enroll the user in the purchased course/exam.</AlertDescription>
                     </Alert>

                     <form onSubmit={handleVerify} className="space-y-4">
                        <div className="space-y-2">
                           <Label htmlFor="admin_notes">Admin Notes (Optional)</Label>
                           <Textarea
                              id="admin_notes"
                              name="admin_notes"
                              rows={3}
                              placeholder="Add any notes about this verification..."
                              value={adminNotes}
                              onChange={(e) => setAdminNotes(e.target.value)}
                           />
                        </div>

                        <DialogFooter className="gap-2">
                           <Button type="button" variant="outline" onClick={onClose}>
                              Cancel
                           </Button>
                           <Button type="button" variant="destructive" onClick={handleReject}>
                              <XCircle className="mr-1 h-4 w-4" />
                              Reject
                           </Button>
                           <Button type="submit">
                              <CheckCircle2 className="mr-1 h-4 w-4" />
                              Verify & Enroll
                           </Button>
                        </DialogFooter>
                     </form>
                  </>
               )}

               {/* Already Verified/Rejected */}
               {status !== 'pending' && (
                  <DialogFooter>
                     <Button variant="outline" onClick={onClose}>
                        Close
                     </Button>
                  </DialogFooter>
               )}
            </div>
         </DialogContent>
      </Dialog>
   );
};

export default VerifyPaymentModal;
