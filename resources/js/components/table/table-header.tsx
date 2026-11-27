import { cn } from '@/lib/utils';
import { TableHeader as DataTableHeader, TableHead, TableRow } from '@/components/ui/table';
import { Table as ReactTable, flexRender } from '@tanstack/react-table';

export const columnClassName = (meta: unknown) =>
   meta && typeof meta === 'object' && 'className' in meta && typeof meta.className === 'string' ? meta.className : undefined;

interface Props {
   table: ReactTable<any>;
   className?: string;
   tableHeadClass?: string;
}

const TableHeader = (props: Props) => {
   const { table, className, tableHeadClass } = props;

   return (
      <DataTableHeader className={className}>
         {table.getHeaderGroups().map((headerGroup) => (
            <TableRow key={headerGroup.id}>
               {headerGroup.headers.map((header) => {
                  return (
                     <TableHead key={header.id} className={cn(tableHeadClass, columnClassName(header.column.columnDef.meta))}>
                        {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                     </TableHead>
                  );
               })}
            </TableRow>
         ))}
      </DataTableHeader>
   );
};

export default TableHeader;
