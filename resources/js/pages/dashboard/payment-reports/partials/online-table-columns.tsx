import { Badge } from '@/components/ui/badge';
import { ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';

const TableColumn = (): ColumnDef<PaymentHistory>[] => {
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
         accessorKey: 'payment_type',
         header: 'Payment Method',
         cell: ({ row }) => (
            <Badge variant="outline" className="capitalize">
               {row.original.payment_type}
            </Badge>
         ),
      },
      {
         accessorKey: 'transaction_id',
         header: 'Transaction ID',
         cell: ({ row }) => <div className="font-mono text-xs">{row.original.transaction_id}</div>,
      },
      {
         accessorKey: 'created_at',
         header: () => <div className="pr-4 text-end">Date</div>,
         cell: ({ row }) => <div className="pr-4 text-end text-sm">{format(new Date(row.original.created_at), 'MMM dd, yyyy HH:mm')}</div>,
      },
   ];
};

export default TableColumn;
