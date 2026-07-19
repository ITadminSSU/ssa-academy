import TableFilter from '@/components/table/table-filter';
import TableFooter from '@/components/table/table-footer';
import TableHeader from '@/components/table/table-header';
import { Card } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableRow } from '@/components/ui/table';
import { usePage } from '@inertiajs/react';
import { SortingState, flexRender, getCoreRowModel, getFilteredRowModel, getSortedRowModel, useReactTable } from '@tanstack/react-table';
import * as React from 'react';
import { CourseUpdateProps } from '../update';
import ActivitySubmissionsTableColumn from './activity-submissions-table-column';

const ActivitySubmissions = () => {
   const { props } = usePage<CourseUpdateProps>();
   const { course, translate, tab, activitySubmissions } = props;
   const [sorting, setSorting] = React.useState<SortingState>([]);

   const table = useReactTable({
      data: activitySubmissions?.data || [],
      columns: ActivitySubmissionsTableColumn(translate),
      onSortingChange: setSorting,
      getCoreRowModel: getCoreRowModel(),
      getSortedRowModel: getSortedRowModel(),
      getFilteredRowModel: getFilteredRowModel(),
      state: { sorting },
   });

   return (
      <Card className="space-y-6 p-4 sm:p-6">
         <TableFilter
            data={activitySubmissions}
            title="Practical activity submissions"
            globalSearch={true}
            tablePageSizes={[10, 15, 20, 25]}
            routeName="courses.edit"
            routeParams={{
               course: course.id,
               tab: tab || 'activity-reviews',
            }}
            className="w-full p-0"
         />

         <Table className="border-border min-w-3xl border-y">
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
                     <TableCell className="h-24 text-center">No practical activity submissions yet.</TableCell>
                  </TableRow>
               )}
            </TableBody>
         </Table>

         <TableFooter
            paginationInfo={activitySubmissions}
            routeName="courses.edit"
            routeParams={{
               course: course.id,
               tab: tab || 'activity-reviews',
            }}
         />
      </Card>
   );
};

export default ActivitySubmissions;
