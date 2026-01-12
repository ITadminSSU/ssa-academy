import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';
import { CheckCircle2, Clock, Eye, XCircle } from 'lucide-react';

const TableColumn = (onVerifyClick: (payment: PaymentHistory) => void): ColumnDef<PaymentHistory>[] => {
   return [
      {
         accessorKey: 'id',
         header: () => <div className="pl-4">ID</div>,
         cell: ({ row }) => <div className="pl-4 font-medium">#{row.original.id}</div>,
      },
      {
         accessorKey: 'user.name',
         header: 'Customer',
         cell: ({ row }) => (
            <div>
               <div className="font-medium">{row.original.user.name}</div>
               <div className="text-muted-foreground text-xs">{row.original.user.email}</div>
            </div>
         ),
      },
      {
         accessorKey: 'purchase.title',
         header: 'Item',
         cell: ({ row }) => <div className="max-w-[200px] truncate">{row.original.purchase?.title || 'N/A'}</div>,
      },
      {
         accessorKey: 'amount',
         header: 'Amount',
         cell: ({ row }) => <div className="font-medium">${Number(row.original.amount).toFixed(2)}</div>,
      },
      {
         accessorKey: 'meta.payment_date',
         header: 'Payment Date',
         cell: ({ row }) => (
            <div className="text-sm">
               {row.original.meta?.payment_date ? format(new Date(row.original.meta.payment_date as string), 'MMM dd, yyyy') : 'N/A'}
            </div>
         ),
      },
      {
         accessorKey: 'meta.status',
         header: 'Status',
         cell: ({ row }) => {
            const status = row.original.meta?.status || 'pending';

            return (
               <Badge variant={status === 'verified' ? 'default' : status === 'rejected' ? 'destructive' : 'secondary'} className="gap-1">
                  {status === 'verified' ? (
                     <CheckCircle2 className="h-3 w-3" />
                  ) : status === 'rejected' ? (
                     <XCircle className="h-3 w-3" />
                  ) : (
                     <Clock className="h-3 w-3" />
                  )}
                  <span>{status as string}</span>
               </Badge>
            );
         },
      },
      {
         accessorKey: 'created_at',
         header: 'Submitted At',
         cell: ({ row }) => <div className="text-sm">{format(new Date(row.original.created_at), 'MMM dd, yyyy HH:mm')}</div>,
      },
      {
         id: 'actions',
         header: () => <div className="pr-4 text-end">Actions</div>,
         cell: ({ row }) => {
            const status = row.original.meta?.status || 'pending';
            return (
               <div className="flex justify-end gap-2 pr-4">
                  <Button size="sm" variant="outline" onClick={() => onVerifyClick(row.original)} className="gap-1">
                     <Eye className="h-3 w-3" />
                     {status === 'pending' ? 'Verify' : 'View'}
                  </Button>
               </div>
            );
         },
      },
   ];
};

export default TableColumn;
