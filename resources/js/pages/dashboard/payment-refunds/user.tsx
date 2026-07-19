import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { Link } from '@inertiajs/react';
import { format } from 'date-fns';
import { ChevronLeft, Edit } from 'lucide-react';
import { ReactNode } from 'react';
import RefundStatusBadge from './partials/refund-status-badge';
import RefundUpdateModal from './partials/refund-update-modal';

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

interface PaymentWithAudit extends PaymentHistory {
   audit_logs?: AuditLog[];
}

interface Props extends SharedData {
   user: User;
   payments: PaymentWithAudit[];
   summary: {
      total_payments: number;
      total_amount: number;
      refund_pending_count: number;
      refunded_count: number;
   };
   statuses: StatusOption[];
}

const PaymentRefundsUser = (props: Props) => {
   const { user, payments, summary, statuses, translate } = props;
   const { dashboard } = translate;

   return (
      <div className="space-y-6">
         <div className="flex items-center gap-3">
            <Button variant="outline" size="sm" asChild>
               <Link href={route('payment-refunds.index')}>
                  <ChevronLeft className="mr-1 h-4 w-4" />
                  {dashboard.back ?? 'Back'}
               </Link>
            </Button>
            <div>
               <h1 className="text-xl font-semibold">{user.name}</h1>
               <p className="text-muted-foreground text-sm">{user.email}</p>
            </div>
            {user.user_type === 'external' && (
               <Button variant="outline" size="sm" className="ml-auto" asChild>
                  <Link href={route('candidates.show', { id: user.id })}>{dashboard.view_candidate ?? 'View Candidate'}</Link>
               </Button>
            )}
         </div>

         <div className="grid gap-4 sm:grid-cols-4">
            <Card>
               <CardContent className="pt-6">
                  <p className="text-muted-foreground text-sm">{dashboard.total_payments ?? 'Total Payments'}</p>
                  <p className="text-2xl font-semibold">{summary.total_payments}</p>
               </CardContent>
            </Card>
            <Card>
               <CardContent className="pt-6">
                  <p className="text-muted-foreground text-sm">{dashboard.total_amount ?? 'Total Amount'}</p>
                  <p className="text-2xl font-semibold">${summary.total_amount.toFixed(2)}</p>
               </CardContent>
            </Card>
            <Card>
               <CardContent className="pt-6">
                  <p className="text-muted-foreground text-sm">{dashboard.refund_pending ?? 'Refund Pending'}</p>
                  <p className="text-2xl font-semibold">{summary.refund_pending_count}</p>
               </CardContent>
            </Card>
            <Card>
               <CardContent className="pt-6">
                  <p className="text-muted-foreground text-sm">{dashboard.refunded ?? 'Refunded'}</p>
                  <p className="text-2xl font-semibold">{summary.refunded_count}</p>
               </CardContent>
            </Card>
         </div>

         <Card>
            <CardHeader>
               <CardTitle>{dashboard.payment_history ?? 'Payment History'}</CardTitle>
            </CardHeader>
            <CardContent>
               {payments.length === 0 ? (
                  <p className="text-muted-foreground text-sm">{dashboard.no_payments ?? 'No payments found for this user.'}</p>
               ) : (
                  <Table>
                     <TableHeader>
                        <TableRow>
                           <TableHead>ID</TableHead>
                           <TableHead>{dashboard.item ?? 'Item'}</TableHead>
                           <TableHead>{dashboard.amount ?? 'Amount'}</TableHead>
                           <TableHead>{dashboard.payment_method ?? 'Method'}</TableHead>
                           <TableHead>{dashboard.refund_status ?? 'Refund Status'}</TableHead>
                           <TableHead>{dashboard.hr_notes ?? 'HR Notes'}</TableHead>
                           <TableHead>Date</TableHead>
                           <TableHead />
                        </TableRow>
                     </TableHeader>
                     <TableBody>
                        {payments.map((payment) => (
                           <TableRow key={payment.id}>
                              <TableCell className="font-medium">#{payment.id}</TableCell>
                              <TableCell>{payment.purchase?.title ?? 'N/A'}</TableCell>
                              <TableCell>${Number(payment.amount).toFixed(2)}</TableCell>
                              <TableCell className="capitalize">{payment.payment_type}</TableCell>
                              <TableCell>
                                 <RefundStatusBadge status={payment.refund_status} />
                              </TableCell>
                              <TableCell className="max-w-[200px] truncate text-sm">{payment.refund_notes ?? '—'}</TableCell>
                              <TableCell className="text-sm">{format(new Date(payment.created_at), 'MMM dd, yyyy')}</TableCell>
                              <TableCell>
                                 <RefundUpdateModal
                                    payment={payment}
                                    statuses={statuses}
                                    auditLogs={payment.audit_logs}
                                    actionComponent={
                                       <Button variant="outline" size="sm">
                                          <Edit className="mr-1 h-4 w-4" />
                                          {dashboard.manage ?? 'Manage'}
                                       </Button>
                                    }
                                 />
                              </TableCell>
                           </TableRow>
                        ))}
                     </TableBody>
                  </Table>
               )}
            </CardContent>
         </Card>
      </div>
   );
};

PaymentRefundsUser.layout = (children: ReactNode) => <DashboardLayout>{children}</DashboardLayout>;

export default PaymentRefundsUser;
