import TableFilter from '@/components/table/table-filter';
import TableFooter from '@/components/table/table-footer';
import TableHeader from '@/components/table/table-header';
import { Card } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableRow } from '@/components/ui/table';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { flexRender, getCoreRowModel, getFilteredRowModel, getSortedRowModel, useReactTable } from '@tanstack/react-table';
import { ReactNode } from 'react';
import StatusFilter from './partials/status-filter';
import TableColumns from './partials/table-columns';

interface StatusOption {
   value: string;
   label: string;
}

interface Props extends SharedData {
   payments: Pagination<PaymentHistory>;
   statuses: StatusOption[];
   filters: {
      refund_status?: string;
      search?: string;
   };
}

const PaymentRefundsIndex = (props: Props) => {
   const { payments, statuses, translate } = props;
   const { dashboard } = translate;

   const table = useReactTable({
      data: payments.data,
      columns: TableColumns(statuses, dashboard),
      getCoreRowModel: getCoreRowModel(),
      getSortedRowModel: getSortedRowModel(),
      getFilteredRowModel: getFilteredRowModel(),
   });

   return (
      <Card>
         <TableFilter
            data={payments}
            title={dashboard.refund_tracking ?? 'Refund Tracking'}
            globalSearch={true}
            tablePageSizes={[10, 15, 20, 25, 50]}
            routeName="payment-refunds.index"
            component={<StatusFilter statuses={statuses} routeName="payment-refunds.index" />}
         />

         <p className="text-muted-foreground border-border border-b px-6 pb-4 text-sm">
            {dashboard.refund_tracking_description ??
               'Track refunds per payment. Use 1-Click Refund for Stripe/PayPal or manually update bank/wire transfer refunds.'}
         </p>

         <Table className="border-border border-y">
            <TableHeader table={table} />

            <TableBody>
               {table.getRowModel().rows?.length ? (
                  table.getRowModel().rows.map((row) => (
                     <TableRow key={row.id}>
                        {row.getVisibleCells().map((cell) => (
                           <TableCell key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</TableCell>
                        ))}
                     </TableRow>
                  ))
               ) : (
                  <TableRow>
                     <TableCell colSpan={8} className="h-24 text-center">
                        {dashboard.no_results_found}
                     </TableCell>
                  </TableRow>
               )}
            </TableBody>
         </Table>

         <TableFooter className="mt-1 p-5 sm:p-7" routeName="payment-refunds.index" paginationInfo={payments} />
      </Card>
   );
};

PaymentRefundsIndex.layout = (children: ReactNode) => <DashboardLayout>{children}</DashboardLayout>;

export default PaymentRefundsIndex;
