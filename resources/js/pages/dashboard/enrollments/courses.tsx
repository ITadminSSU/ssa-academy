import TableFilter from '@/components/table/table-filter';
import TableFooter from '@/components/table/table-footer';
import TableHeader from '@/components/table/table-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableRow } from '@/components/ui/table';
import { useAuth } from '@/hooks/use-auth';
import DashboardLayout from '@/layouts/dashboard/layout';
import { getQueryParams } from '@/lib/route';
import { SharedData } from '@/types/global';
import { router, usePage } from '@inertiajs/react';
import { flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table';
import { ReactNode, useEffect, useMemo, useState } from 'react';
import AdminTableColumn from './partials/admin-table-columns';
import EnrollmentCourseChart, { EnrollmentChartRow, EnrollmentLabelOption } from './partials/enrollment-course-chart';
import { EnrollmentListControls } from './partials/enrollment-list-controls';
import EnrollmentModal from './partials/enrollment-modal';
import InstructorTableColumn from './partials/instructor-table-columns';

interface Props extends SharedData {
   enrollments: Pagination<CourseEnrollment>;
   enrollmentChart?: EnrollmentChartRow[];
   studentLabels?: EnrollmentLabelOption[];
   matchingEnrollmentIds?: number[];
}

const Courses = (props: Props) => {
   const { isAdmin } = useAuth();
   const page = usePage();
   const params = getQueryParams(page.url);
   const { translate, enrollments, enrollmentChart = [], studentLabels = [], matchingEnrollmentIds = [] } = props;
   const { button, dashboard } = translate;
   const [selectedIds, setSelectedIds] = useState<number[]>([]);
   const filterKey = `${params.search ?? ''}|${params.tags ?? ''}`;

   useEffect(() => {
      setSelectedIds([]);
   }, [filterKey]);

   const matchingSet = useMemo(() => new Set(matchingEnrollmentIds), [matchingEnrollmentIds]);
   const selectedCount = selectedIds.filter((id) => matchingSet.has(id)).length;
   const sortColumn = params.sort ?? '';
   const sortDirection = params.direction === 'desc' ? 'desc' : 'asc';

   const onSort = (column: string) => {
      const next: Record<string, string> = { ...params };
      next.sort = column;
      next.direction = params.sort === column && params.direction === 'asc' ? 'desc' : 'asc';
      delete next.page;

      router.get(route('course-enrollments.index'), next, {
         preserveScroll: true,
         preserveState: true,
      });
   };

   const controls: EnrollmentListControls = {
      sort: sortColumn ? { column: sortColumn, direction: sortDirection } : null,
      onSort,
      selection: {
         matchingIds: matchingEnrollmentIds,
         selectedIds,
         onToggleAll: (checked) => setSelectedIds(checked ? matchingEnrollmentIds : []),
         onToggle: (id) => setSelectedIds((current) => (current.includes(id) ? current.filter((item) => item !== id) : [...current, id])),
      },
   };

   const table = useReactTable({
      data: enrollments.data,
      columns: isAdmin
         ? AdminTableColumn('course', translate, 'enrollments.destroy', controls)
         : InstructorTableColumn('course', translate, controls),
      getCoreRowModel: getCoreRowModel(),
   });

   return (
      <div>
         <EnrollmentModal type="course" title="Add New Course Enrollment" handler={<Button>{button.add_new_enrollment}</Button>} />

         <Separator className="my-6" />

         <EnrollmentCourseChart rows={enrollmentChart} labels={studentLabels} />

         <Card>
            <p className="text-muted-foreground px-6 pt-4 text-sm">{selectedCount} selected</p>
            <TableFilter
               data={enrollments}
               title={dashboard.course_list}
               globalSearch={true}
               tablePageSizes={[10, 15, 20, 25]}
               routeName="course-enrollments.index"
               exportPath="course-enrollments.export"
            />

            <div className="overflow-x-auto">
               <Table className="border-border border-y">
                  <TableHeader table={table} />

                  <TableBody>
                     {enrollments.data && enrollments.data.length > 0 ? (
                        table.getRowModel().rows.map((row) => (
                           <TableRow key={row.id} data-state={row.getIsSelected() && 'selected'}>
                              {row.getVisibleCells().map((cell) => (
                                 <TableCell key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</TableCell>
                              ))}
                           </TableRow>
                        ))
                     ) : (
                        <TableRow>
                           <TableCell className="h-24 text-center">{dashboard.no_results}</TableCell>
                        </TableRow>
                     )}
                  </TableBody>
               </Table>
            </div>

            <TableFooter className="p-5 sm:p-7" routeName="course-enrollments.index" paginationInfo={enrollments} />
         </Card>
      </div>
   );
};

Courses.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default Courses;
