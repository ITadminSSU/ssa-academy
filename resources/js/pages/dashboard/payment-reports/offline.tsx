import TableFilter from '@/components/table/table-filter';
import TableFooter from '@/components/table/table-footer';
import TableHeader from '@/components/table/table-header';
import { Card } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableRow } from '@/components/ui/table';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { flexRender, getCoreRowModel, getFilteredRowModel, getSortedRowModel, useReactTable } from '@tanstack/react-table';
import { ReactNode, useState } from 'react';
import TableColumn from './partials/offline-table-columns';
import VerifyPaymentModal from './partials/verify-payment-modal';

interface Props extends SharedData {
   payments: Pagination<PaymentHistory>;
}

const Offline = ({ payments, translate }: Props) => {
   const { dashboard } = translate;
   const [selectedPayment, setSelectedPayment] = useState<PaymentHistory | null>(null);
   const [isModalOpen, setIsModalOpen] = useState(false);

   const handleVerifyClick = (payment: PaymentHistory) => {
      setSelectedPayment(payment);
      setIsModalOpen(true);
   };

   const table = useReactTable({
      data: payments.data,
      columns: TableColumn(handleVerifyClick),
      getCoreRowModel: getCoreRowModel(),
      getSortedRowModel: getSortedRowModel(),
      getFilteredRowModel: getFilteredRowModel(),
   });

   return (
      <>
         <Card>
            <TableFilter
               data={payments}
               title="Offline Payment Report"
               globalSearch={true}
               tablePageSizes={[10, 15, 20, 25, 50]}
               routeName="payment-reports.offline.index"
               className="w-full"
            />

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
                        <TableCell colSpan={8} className="h-24 text-center">
                           {dashboard.no_results}
                        </TableCell>
                     </TableRow>
                  )}
               </TableBody>
            </Table>

            <TableFooter className="mt-1 p-5 sm:p-7" routeName="payment-reports.offline.index" paginationInfo={payments} />
         </Card>

         {selectedPayment && (
            <VerifyPaymentModal
               payment={selectedPayment}
               isOpen={isModalOpen}
               onClose={() => {
                  setIsModalOpen(false);
                  setSelectedPayment(null);
               }}
            />
         )}
      </>
   );
};

Offline.layout = (children: ReactNode) => <DashboardLayout>{children}</DashboardLayout>;

export default Offline;
