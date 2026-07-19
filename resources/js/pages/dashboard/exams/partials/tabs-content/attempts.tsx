import TableFilter from '@/components/table/table-filter';
import TableFooter from '@/components/table/table-footer';
import TableHeader from '@/components/table/table-header';
import SsuStatCard from '@/components/ssu-stat-card';
import { Card } from '@/components/ui/card';
import { CheckCircle2, CircleDashed, Percent, Target } from 'lucide-react';
import { Table, TableBody, TableCell, TableRow } from '@/components/ui/table';
import { usePage } from '@inertiajs/react';
import { SortingState, flexRender, getCoreRowModel, getFilteredRowModel, getSortedRowModel, useReactTable } from '@tanstack/react-table';
import * as React from 'react';
import { ExamUpdateProps } from '../../update';
import ExamAttemptReview from '../exam-attempt-review';
import ExamAttemptTableColumn from '../exam-attempt-table-columns';

const Attempts = () => {
   const { attempts, exam, attempt } = usePage<ExamUpdateProps>().props;
   const [sorting, setSorting] = React.useState<SortingState>([]);

   const table = useReactTable({
      data: attempts.data,
      columns: ExamAttemptTableColumn(),
      onSortingChange: setSorting,
      getCoreRowModel: getCoreRowModel(),
      getSortedRowModel: getSortedRowModel(),
      getFilteredRowModel: getFilteredRowModel(),
      state: { sorting },
   });

   return (
      <div className="space-y-4">
         {/* Exam Attempts Summary */}
         <div className="grid gap-4 md:grid-cols-4">
            <SsuStatCard title="Total Attempts" value={attempts.total} toneIndex={0} icon={<Target className="h-6 w-6" />} />
            <SsuStatCard
               title="Completed"
               value={attempts.data.filter((a) => a.status === 'completed').length}
               toneIndex={1}
               icon={<CheckCircle2 className="h-6 w-6" />}
            />
            <SsuStatCard
               title="In Progress"
               value={attempts.data.filter((a) => a.status === 'in_progress').length}
               toneIndex={2}
               icon={<CircleDashed className="h-6 w-6" />}
            />
            <SsuStatCard
               title="Pass Rate"
               value={`${
                  attempts.data.length > 0
                     ? (
                          (attempts.data.filter((a) => a.is_passed && a.status === 'completed').length /
                             attempts.data.filter((a) => a.status === 'completed').length) *
                             100 || 0
                       ).toFixed(1)
                     : 0
               }%`}
               toneIndex={0}
               icon={<Percent className="h-6 w-6" />}
            />
         </div>

         {/* Attempts Table */}
         {attempt ? (
            <ExamAttemptReview attempt={attempt} />
         ) : (
            <Card className="ssu-table-shell">
               <TableFilter
                  data={attempts}
                  title="Exam Attempts"
                  globalSearch={true}
                  tablePageSizes={[10, 15, 20, 25]}
                  routeName="exams.edit"
                  routeParams={{ exam: exam.id, tab: 'attempts' }}
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
                           <TableCell colSpan={table.getAllColumns().length} className="h-24 text-center">
                              No exam attempts found.
                           </TableCell>
                        </TableRow>
                     )}
                  </TableBody>
               </Table>

               <TableFooter
                  className="p-5 sm:p-7"
                  routeName="exams.edit"
                  paginationInfo={attempts}
                  routeParams={{ exam: exam.id, tab: 'attempts' }}
               />
            </Card>
         )}
      </div>
   );
};

export default Attempts;
