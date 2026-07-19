import TableFilter from '@/components/table/table-filter';
import TableFooter from '@/components/table/table-footer';
import TableHeader from '@/components/table/table-header';
import { Button } from '@/components/ui/button';
import SsuStatCard from '@/components/ssu-stat-card';
import { Card } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableRow } from '@/components/ui/table';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { Head, Link, usePage } from '@inertiajs/react';
import { SortingState, flexRender, getCoreRowModel, getFilteredRowModel, getSortedRowModel, useReactTable } from '@tanstack/react-table';
import { Archive, BookOpen, Edit, Eye, Plus } from 'lucide-react';
import * as React from 'react';
import { ReactNode } from 'react';
import TableColumn from './partials/table-columns';

interface BlogsPageProps extends SharedData {
   blogs: Pagination<Blog>;
   categories: BlogCategory[];
   statuses: Record<string, string>;
   statistics: {
      total: number;
      published: number;
      draft: number;
      archived: number;
      popular: number;
   };
}

const BlogsIndex = () => {
   const { props } = usePage<BlogsPageProps>();
   const { blogs, statistics, translate } = props;
   const { dashboard, frontend } = translate;

   const [sorting, setSorting] = React.useState<SortingState>([]);

   const table = useReactTable({
      data: blogs.data,
      columns: TableColumn(props.translate),
      onSortingChange: setSorting,
      getCoreRowModel: getCoreRowModel(),
      getSortedRowModel: getSortedRowModel(),
      getFilteredRowModel: getFilteredRowModel(),
      state: { sorting },
   });

   return (
      <>
         <Head title={dashboard.blog} />

         <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
               <h1 className="text-xl font-semibold">{dashboard.blog}</h1>

               <Button asChild>
                  <Link href={route('blogs.create')}>
                     <Plus className="mr-2 h-4 w-4" />
                     {dashboard.add_new_blog}
                  </Link>
               </Button>
            </div>

            <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
               <SsuStatCard title={dashboard.total_blogs} value={statistics.total} toneIndex={0} icon={<BookOpen className="h-6 w-6" />} />
               <SsuStatCard title={dashboard.published} value={statistics.published} toneIndex={1} icon={<Eye className="h-6 w-6" />} />
               <SsuStatCard title={dashboard.draft} value={statistics.draft} toneIndex={2} icon={<Edit className="h-6 w-6" />} />
               <SsuStatCard title={dashboard.archived} value={statistics.archived} toneIndex={0} icon={<Archive className="h-6 w-6" />} />
            </div>

            <Card className="ssu-table-shell">
               <TableFilter data={blogs} title={dashboard.blog} globalSearch={true} tablePageSizes={[10, 15, 20, 25]} routeName="blogs.index" />

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
                              {frontend.no_results}
                           </TableCell>
                        </TableRow>
                     )}
                  </TableBody>
               </Table>

               <TableFooter className="p-5 sm:p-7" routeName="blogs.index" paginationInfo={blogs} />
            </Card>
         </div>
      </>
   );
};

BlogsIndex.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default BlogsIndex;
