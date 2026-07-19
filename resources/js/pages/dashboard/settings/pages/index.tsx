import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableRow } from '@/components/ui/table';
import TableHeader from '@/components/table/table-header';
import DashboardLayout from '@/layouts/dashboard/layout';
import { PageSelectProps } from '@/types/page';
import { Head } from '@inertiajs/react';
import { flexRender, getCoreRowModel, getFilteredRowModel, getSortedRowModel, useReactTable } from '@tanstack/react-table';
import * as React from 'react';
import CustomPageCreateForm from './partials/custom-page-create-form';
import CustomTableColumn from './partials/custom-pages-table-columns';
import SsuLandingSettings from './partials/ssu-landing-settings';

const Pages = ({ pages, ssuLandingPage, translate }: PageSelectProps) => {
   const { settings } = translate;

   const customPages = React.useMemo(() => pages.filter((page) => page.type === 'inner_page'), [pages]);
   const customColumns = React.useMemo(() => CustomTableColumn(translate), [translate]);

   const customPagesTable = useReactTable({
      data: customPages,
      columns: customColumns,
      getCoreRowModel: getCoreRowModel(),
      getSortedRowModel: getSortedRowModel(),
      getFilteredRowModel: getFilteredRowModel(),
   });

   return (
      <>
         <Head title={settings.page_settings} />

         <div className="mx-auto space-y-10 md:px-3">
            <div className="mb-6 flex items-center justify-between">
               <h1 className="text-2xl font-bold">{settings.page_settings}</h1>
            </div>

            <SsuLandingSettings landingPage={ssuLandingPage} />

            <Card>
               <div className="flex flex-col items-start justify-between gap-4 p-4 md:flex-row md:items-center">
                  <div>
                     <h2 className="text-lg font-medium">{settings.custom_pages}</h2>
                     <p className="text-muted-foreground text-sm">
                        Page slug will be the page path. Example:{' '}
                        <span className="text-secondary-foreground">http://app-domain.com/cookie-policy</span>
                     </p>
                  </div>

                  <CustomPageCreateForm title="Add New Page" actionComponent={<Button>Add New Page</Button>} />
               </div>

               <Table className="border-border border-y last:border-b-0">
                  <TableHeader table={customPagesTable} />

                  <TableBody>
                     {customPagesTable.getRowModel().rows?.length ? (
                        customPagesTable.getRowModel().rows.map((row) => (
                           <TableRow key={row.id} data-state={row.getIsSelected() && 'selected'} className="hover:bg-secondary-lighter">
                              {row.getVisibleCells().map((cell) => (
                                 <TableCell key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</TableCell>
                              ))}
                           </TableRow>
                        ))
                     ) : (
                        <TableRow>
                           <TableCell className="h-24 text-center">No results.</TableCell>
                        </TableRow>
                     )}
                  </TableBody>
               </Table>
            </Card>
         </div>
      </>
   );
};

Pages.layout = (page: React.ReactNode) => <DashboardLayout children={page} />;

export default Pages;
