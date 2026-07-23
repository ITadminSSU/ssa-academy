import TableFilter from '@/components/table/table-filter';
import TableFooter from '@/components/table/table-footer';
import TableHeader from '@/components/table/table-header';
import { Card } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableRow } from '@/components/ui/table';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { Head } from '@inertiajs/react';
import { SortingState, flexRender, getCoreRowModel, getFilteredRowModel, getSortedRowModel, useReactTable } from '@tanstack/react-table';
import * as React from 'react';
import { ReactNode } from 'react';
import CreateForm from './Partials/create-form';
import RoleStats from './Partials/role-stats';
import TableColumn from './Partials/table-columns';

interface RoleFilterOption {
   value: string;
   label: string;
}

interface RoleCounts {
   all: number;
   admin: number;
   internal_employee: number;
   external: number;
   trainer: number;
}

interface Props extends SharedData {
   users: Pagination<User>;
   roleFilters: RoleFilterOption[];
   roleCounts: RoleCounts;
   filters: {
      role_filter?: string;
      search?: string;
   };
   protectedUserId?: number | null;
}

const Index = (props: Props) => {
   const [sorting, setSorting] = React.useState<SortingState>([]);
   const { translate } = props;
   const { dashboard } = translate;

   const text = (value: string | undefined, fallback: string) => value?.trim() || fallback;

   const table = useReactTable({
      data: props.users.data,
      columns: TableColumn(translate, props.protectedUserId),
      onSortingChange: setSorting,
      getCoreRowModel: getCoreRowModel(),
      getSortedRowModel: getSortedRowModel(),
      getFilteredRowModel: getFilteredRowModel(),
      state: { sorting },
   });

   return (
      <>
         <Head title={text(dashboard.user_management, 'User Management')} />

         <div className="space-y-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
               <div>
                  <h1 className="text-xl font-semibold">{text(dashboard.user_management, 'User Management')}</h1>
                  <p className="text-muted-foreground mt-1 max-w-3xl text-sm">
                     {text(
                        dashboard.all_users_description,
                        'Manage admins, internal employees, external learners, and trainers. The primary admin account is protected from deletion.',
                     )}
                  </p>
               </div>

               <CreateForm />
            </div>

            <RoleStats roleCounts={props.roleCounts} roleFilters={props.roleFilters} routeName="users.index" />

            <Card>
               <TableFilter
                  data={props.users}
                  title={text(dashboard.all_users, 'All Users')}
                  globalSearch={true}
                  tablePageSizes={[10, 15, 20, 25]}
                  routeName="users.index"
               />

               <Table className="border-border border-y">
                  <TableHeader table={table} tableHeadClass="px-6" />

                  <TableBody>
                     {table.getRowModel().rows.map((row) => (
                        <TableRow key={row.id} data-state={row.getIsSelected() && 'selected'}>
                           {row.getVisibleCells().map((cell) => (
                              <TableCell key={cell.id} className="px-6">
                                 {flexRender(cell.column.columnDef.cell, cell.getContext())}
                              </TableCell>
                           ))}
                        </TableRow>
                     ))}
                  </TableBody>
               </Table>

               {table.getRowModel().rows.length <= 0 && (
                  <p className="border-border w-full border-b px-6 py-10 text-center">{dashboard.no_results_found}</p>
               )}

               <TableFooter className="p-5 sm:p-7" routeName="users.index" paginationInfo={props.users} />
            </Card>
         </div>
      </>
   );
};

Index.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default Index;
