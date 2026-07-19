import { Button } from '@/components/ui/button';
import { ColumnDef } from '@tanstack/react-table';
import { Link } from '@inertiajs/react';
import { format } from 'date-fns';
import { Edit } from 'lucide-react';
import RefundStatusBadge from './refund-status-badge';
import ProcessRefundButton from './process-refund-button';
import RefundUpdateModal from './refund-update-modal';

interface StatusOption {
   value: string;
   label: string;
}

const TableColumns = (statuses: StatusOption[], dashboard: Record<string, string>): ColumnDef<PaymentHistory>[] => {
   return [
      {
         accessorKey: 'id',
         header: () => <div className="pl-4">ID</div>,
         cell: ({ row }) => <div className="pl-4 font-medium">#{row.original.id}</div>,
      },
      {
         accessorKey: 'user.name',
         header: dashboard.customer ?? 'Customer',
         cell: ({ row }) => (
            <div>
               <Link href={route('payment-refunds.user', { user: row.original.user_id })} className="font-medium hover:underline">
                  {row.original.user.name}
               </Link>
               <div className="text-muted-foreground text-xs">{row.original.user.email}</div>
            </div>
         ),
      },
      {
         accessorKey: 'purchase.title',
         header: dashboard.item ?? 'Item',
         cell: ({ row }) => <div className="max-w-[200px] truncate">{row.original.purchase?.title || 'N/A'}</div>,
      },
      {
         accessorKey: 'amount',
         header: dashboard.amount ?? 'Amount',
         cell: ({ row }) => <div className="font-medium">${Number(row.original.amount).toFixed(2)}</div>,
      },
      {
         accessorKey: 'payment_type',
         header: dashboard.payment_method ?? 'Method',
         cell: ({ row }) => <span className="text-sm capitalize">{row.original.payment_type}</span>,
      },
      {
         accessorKey: 'refund_status',
         header: dashboard.refund_status ?? 'Refund Status',
         cell: ({ row }) => <RefundStatusBadge status={row.original.refund_status} />,
      },
      {
         accessorKey: 'created_at',
         header: () => <div className="text-end">Date</div>,
         cell: ({ row }) => <div className="text-end text-sm">{format(new Date(row.original.created_at), 'MMM dd, yyyy HH:mm')}</div>,
      },
      {
         id: 'actions',
         header: () => <div className="pr-4 text-end">{dashboard.action ?? 'Action'}</div>,
         cell: ({ row }) => (
            <div className="flex items-center justify-end gap-2 pr-4">
               <ProcessRefundButton payment={row.original} label={dashboard.one_click_refund ?? '1-Click Refund'} />
               <RefundUpdateModal
                  payment={row.original}
                  statuses={statuses}
                  actionComponent={
                     <Button variant="outline" size="sm">
                        <Edit className="mr-1 h-4 w-4" />
                        {dashboard.manage ?? 'Manage'}
                     </Button>
                  }
               />
            </div>
         ),
      },
   ];
};

export default TableColumns;
