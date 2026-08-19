import TableFilter from '@/components/table/table-filter';
import TableFooter from '@/components/table/table-footer';
import TableHeader from '@/components/table/table-header';
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
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableRow } from '@/components/ui/table';
import DashboardLayout from '@/layouts/dashboard/layout';
import { Head, router } from '@inertiajs/react';
import { RowSelectionState, SortingState, flexRender, getCoreRowModel, getFilteredRowModel, getSortedRowModel, useReactTable } from '@tanstack/react-table';
import { Plus, Trash2, Upload } from 'lucide-react';
import * as React from 'react';
import CouponForm from './coupon-form';
import CouponImportForm from './coupon-import-form';
import CouponTableColumns from './coupon-table-columns';

interface Props {
   courses: Course[];
   coupons: Pagination<CourseCoupon>;
}

const CouponsIndex = ({ coupons, courses }: Props) => {
   const [sorting, setSorting] = React.useState<SortingState>([]);
   const [rowSelection, setRowSelection] = React.useState<RowSelectionState>({});
   const [deleteTarget, setDeleteTarget] = React.useState<{ type: 'single' | 'bulk'; id?: number } | null>(null);

   const handleDelete = (id: number) => {
      setDeleteTarget({ type: 'single', id });
   };

   const table = useReactTable({
      data: coupons.data,
      columns: CouponTableColumns({ courses, onDelete: handleDelete }),
      onSortingChange: setSorting,
      onRowSelectionChange: setRowSelection,
      getCoreRowModel: getCoreRowModel(),
      getSortedRowModel: getSortedRowModel(),
      getFilteredRowModel: getFilteredRowModel(),
      state: { sorting, rowSelection },
   });

   const selectedCount = Object.keys(rowSelection).length;

   const confirmDelete = () => {
      if (!deleteTarget) return;

      if (deleteTarget.type === 'single' && deleteTarget.id) {
         router.delete(route('course-coupons.destroy', deleteTarget.id), {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
         });
      } else if (deleteTarget.type === 'bulk') {
         const selectedIds = table
            .getSelectedRowModel()
            .rows.map((row) => row.original.id);

         router.post(route('course-coupons.bulk-destroy'), { ids: selectedIds }, {
            preserveScroll: true,
            onSuccess: () => {
               setRowSelection({});
               setDeleteTarget(null);
            },
         });
      }
   };

   return (
      <>
         <Head title="Course Coupons" />

         <div className="space-y-6">
            <div className="flex items-center justify-between">
               <div>
                  <h1 className="text-3xl font-bold text-foreground">Course Coupons</h1>
                  <p className="mt-1 text-sm text-muted-foreground">Manage discount coupons for your Courses</p>
               </div>
               <div className="flex items-center gap-2">
                  <CouponImportForm
                     courses={courses}
                     handler={
                        <Button variant="outline">
                           <Upload className="mr-2 h-4 w-4" />
                           Import Coupons
                        </Button>
                     }
                  />
                  <CouponForm
                     title="Create Coupon"
                     courses={courses}
                     handler={
                        <Button>
                           <Plus className="mr-2 h-4 w-4" />
                           Add Coupon
                        </Button>
                     }
                  />
               </div>
            </div>

            <Card>
               <div className="flex items-center justify-between">
                  <TableFilter
                     data={coupons}
                     title="Coupon List"
                     globalSearch={true}
                     tablePageSizes={[10, 15, 20, 25]}
                     routeName="course-coupons.index"
                  />
                  {selectedCount > 0 && (
                     <div className="flex items-center gap-2 pr-5">
                        <span className="text-sm text-muted-foreground">{selectedCount} selected</span>
                        <Button
                           variant="destructive"
                           size="sm"
                           onClick={() => setDeleteTarget({ type: 'bulk' })}
                        >
                           <Trash2 className="mr-1 h-4 w-4" />
                           Delete Selected
                        </Button>
                     </div>
                  )}
               </div>

               <Table className="border-border border-y">
                  <TableHeader table={table} />

                  <TableBody>
                     {table.getRowModel().rows?.length ? (
                        table.getRowModel().rows.map((row) => (
                           <TableRow key={row.id} data-state={row.getIsSelected() && 'selected'}>
                              {row.getVisibleCells().map((cell) => (
                                 <TableCell key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</TableCell>
                              ))}
                           </TableRow>
                        ))
                     ) : (
                        <TableRow>
                           <TableCell colSpan={table.getAllColumns().length} className="h-24 text-center">
                              No coupons found.
                           </TableCell>
                        </TableRow>
                     )}
                  </TableBody>
               </Table>

               <TableFooter className="p-5 sm:p-7" routeName="course-coupons.index" paginationInfo={coupons} />
            </Card>
         </div>

         <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
            <AlertDialogContent>
               <AlertDialogHeader>
                  <AlertDialogTitle>Are you sure?</AlertDialogTitle>
                  <AlertDialogDescription>
                     {deleteTarget?.type === 'bulk'
                        ? `This will permanently delete ${selectedCount} selected coupon(s). This action cannot be undone.`
                        : 'This will permanently delete this coupon. This action cannot be undone.'}
                  </AlertDialogDescription>
               </AlertDialogHeader>
               <AlertDialogFooter>
                  <AlertDialogCancel>Cancel</AlertDialogCancel>
                  <AlertDialogAction onClick={confirmDelete} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                     Delete
                  </AlertDialogAction>
               </AlertDialogFooter>
            </AlertDialogContent>
         </AlertDialog>
      </>
   );
};

CouponsIndex.layout = (page: React.ReactNode) => <DashboardLayout>{page}</DashboardLayout>;

export default CouponsIndex;
