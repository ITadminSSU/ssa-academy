import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { ColumnDef } from '@tanstack/react-table';
import { format, isFuture, isPast, parseISO } from 'date-fns';
import { Copy, Eye, Pencil, Trash2 } from 'lucide-react';
import CouponForm from './coupon-form';
import CouponUsagesModal from './coupon-usages-modal';

interface CouponTableColumnsProps {
   courses: Course[];
   onDelete: (id: number) => void;
}

const CouponTableColumns = ({ courses, onDelete }: CouponTableColumnsProps): ColumnDef<CourseCoupon>[] => {
   const getCouponStatus = (coupon: CourseCoupon) => {
      if (!coupon.is_active) return { label: 'Inactive', variant: 'secondary' as const };
      if (coupon.valid_to && isPast(parseISO(coupon.valid_to))) return { label: 'Expired', variant: 'destructive' as const };
      if (coupon.valid_from && isFuture(parseISO(coupon.valid_from))) return { label: 'Scheduled', variant: 'secondary' as const };
      if (coupon.usage_limit && coupon.used_count >= coupon.usage_limit) return { label: 'Used Up', variant: 'destructive' as const };
      return { label: 'Active', variant: 'default' as const };
   };

   const copyCouponCode = (code: string) => {
      navigator.clipboard.writeText(code);
      alert('Coupon code copied to clipboard!');
   };

   return [
      {
         id: 'select',
         header: ({ table }) => (
            <div className="pl-4">
               <Checkbox
                  checked={table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate')}
                  onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
                  aria-label="Select all"
               />
            </div>
         ),
         cell: ({ row }) => (
            <div className="pl-4">
               <Checkbox
                  checked={row.getIsSelected()}
                  onCheckedChange={(value) => row.toggleSelected(!!value)}
                  aria-label="Select row"
               />
            </div>
         ),
         enableSorting: false,
      },
      {
         accessorKey: 'code',
         header: 'Coupon Code',
         cell: ({ row }) => (
            <div className="flex items-center gap-2">
               <code className="rounded bg-muted px-2 py-1 font-bold">{row.original.code}</code>
               <Button variant="ghost" size="icon" className="h-6 w-6" onClick={() => copyCouponCode(row.original.code)}>
                  <Copy className="h-3 w-3" />
               </Button>
            </div>
         ),
      },
      {
         accessorKey: 'discount',
         header: 'Discount',
         cell: ({ row }) => (
            <Badge variant="outline">
               {row.original.discount_type === 'percentage' ? `${row.original.discount}% OFF` : `$${row.original.discount} OFF`}
            </Badge>
         ),
      },
      {
         accessorKey: 'course',
         header: 'Course',
         cell: ({ row }) =>
            row.original.course ? (
               <span className="font-medium">{row.original.course.title}</span>
            ) : (
               <span className="text-primary font-medium">Global Coupon</span>
            ),
      },
      {
         accessorKey: 'usage',
         header: 'Usage',
         cell: ({ row }) => {
            const limit = row.original.usage_limit;
            const used = row.original.used_count ?? 0;

            return limit != null && limit > 0 ? (
               <span>
                  {used} / {limit}
               </span>
            ) : (
               <span>{used} used</span>
            );
         },
      },
      {
         accessorKey: 'valid_from',
         header: 'Valid From',
         cell: ({ row }) => (row.original.valid_from ? format(parseISO(row.original.valid_from), 'MMM dd, yyyy HH:mm') : '-'),
      },
      {
         accessorKey: 'valid_to',
         header: 'Valid To',
         cell: ({ row }) => (row.original.valid_to ? format(parseISO(row.original.valid_to), 'MMM dd, yyyy HH:mm') : '-'),
      },
      {
         accessorKey: 'status',
         header: 'Status',
         cell: ({ row }) => {
            const status = getCouponStatus(row.original);
            return <Badge variant={status.variant}>{status.label}</Badge>;
         },
      },
      {
         id: 'actions',
         header: () => <p className="pr-4 text-end">Actions</p>,
         cell: ({ row }) => {
            const coupon = row.original;

            return (
               <div className="flex items-center justify-end gap-1 py-2 pr-4">
                  <CouponUsagesModal
                     coupon={coupon}
                     handler={
                        <Button size="icon" variant="ghost" className="h-8 w-8" title="View usages">
                           <Eye className="h-4 w-4" />
                        </Button>
                     }
                  />
                  <CouponForm
                     title="Edit Coupon"
                     coupon={coupon}
                     courses={courses}
                     handler={
                        <Button size="icon" variant="secondary" className="h-8 w-8" title="Edit">
                           <Pencil className="h-4 w-4" />
                        </Button>
                     }
                  />
                  <Button
                     size="icon"
                     variant="ghost"
                     className="h-8 w-8 text-destructive hover:text-destructive"
                     title="Delete"
                     onClick={() => onDelete(coupon.id)}
                  >
                     <Trash2 className="h-4 w-4" />
                  </Button>
               </div>
            );
         },
      },
   ];
};

export default CouponTableColumns;
