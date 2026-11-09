import TableFilter from '@/components/table/table-filter';
import TableFooter from '@/components/table/table-footer';
import TableHeader from '@/components/table/table-header';
import { Card } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableRow } from '@/components/ui/table';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { flexRender, getCoreRowModel, getFilteredRowModel, getSortedRowModel, useReactTable } from '@tanstack/react-table';
import { ReactNode } from 'react';
import ActiveSubscriptionColumns, { ActiveSubscriptionRow } from './partials/active-subscription-columns';
import ActiveSubscriptionFilters from './partials/active-subscription-filters';
import SubscriptionStatusChart, { SubscriptionSummaryRow } from './partials/subscription-status-chart';

interface CourseOption {
   id: number;
   title: string;
}

interface Props extends SharedData {
   subscriptions: Pagination<ActiveSubscriptionRow>;
   courses: CourseOption[];
   subscriptionSummary?: SubscriptionSummaryRow[];
   error?: string | null;
}

const ActiveSubscriptions = ({ subscriptions, courses, subscriptionSummary = [], error, translate }: Props) => {
   const { dashboard } = translate;

   const table = useReactTable({
      data: subscriptions.data,
      columns: ActiveSubscriptionColumns(),
      getCoreRowModel: getCoreRowModel(),
      getSortedRowModel: getSortedRowModel(),
      getFilteredRowModel: getFilteredRowModel(),
   });

   return (
      <div className="space-y-6">
         <SubscriptionStatusChart rows={subscriptionSummary} />
         <Card>
         <TableFilter
            data={subscriptions}
            title="Active subscriptions"
            globalSearch={true}
            tablePageSizes={[10, 15, 20, 25, 50]}
            routeName="payment-reports.active-subscriptions.index"
            exportPath="payment-reports.active-subscriptions.export"
            component={<ActiveSubscriptionFilters courses={courses} />}
         />

         <p className="text-muted-foreground border-border border-b px-6 pb-4 text-sm">
            Students in a paid Stripe period right now. This list is read from Stripe and does not change enrollments or payments.
         </p>

         {error ? <p className="text-destructive px-6 py-4 text-sm">{error}</p> : null}

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
                     <TableCell colSpan={6} className="h-24 text-center">
                        {dashboard.no_results}
                     </TableCell>
                  </TableRow>
               )}
            </TableBody>
         </Table>

         <TableFooter className="mt-1 p-5 sm:p-7" routeName="payment-reports.active-subscriptions.index" paginationInfo={subscriptions} />
         </Card>
      </div>
   );
};

ActiveSubscriptions.layout = (children: ReactNode) => <DashboardLayout>{children}</DashboardLayout>;

export default ActiveSubscriptions;
