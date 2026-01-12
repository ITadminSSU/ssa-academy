import DeleteModal from '@/components/inertia/delete-modal';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { ColumnDef } from '@tanstack/react-table';
import { ArrowUpDown, Download, Eye, Pencil, Trash2 } from 'lucide-react';
import EditForm from './edit-form';

const TableColumn = (translate: LanguageTranslations): ColumnDef<User>[] => {
   const { table, common } = translate;

   return [
      {
         accessorKey: 'name',
         header: ({ column }) => {
            return (
               <div className="flex items-center">
                  <Button variant="ghost" className="p-0 hover:bg-transparent" onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}>
                     {table.name}
                     <ArrowUpDown />
                  </Button>
               </div>
            );
         },
         cell: ({ row }) => (
            <div className="flex items-center gap-2">
               <Avatar className="h-11 w-11">
                  <AvatarImage src={row.original.photo || ''} className="object-cover" />
                  <AvatarFallback>CN</AvatarFallback>
               </Avatar>

               <div>
                  <p className="mb-0.5 text-base font-medium">{row.original.name}</p>
                  <p className="text-muted-foreground text-xs">{row.original.email}</p>
               </div>
            </div>
         ),
      },
      {
         accessorKey: 'status',
         header: table.status,
         cell: ({ row }) => (
            <div className="capitalize">
               <span>{row.original.status === 1 ? common.active : common.inactive}</span>
            </div>
         ),
      },
      {
         accessorKey: 'role',
         header: table.role,
         cell: ({ row }) => (
            <div className="capitalize">
               <span>{row.original.role}</span>
            </div>
         ),
      },
      {
         id: 'professional_type',
         header: 'Professional Type',
         cell: ({ row }) => {
            const user = row.original;
            const professionalType = user.professional_type?.name || 'N/A';
            const otherType = user.professional_type_other;

            return (
               <div>
                  {professionalType === 'Other' && otherType ? (
                     <div>
                        <span className="text-sm">{professionalType}</span>
                        <br />
                        <span className="text-xs text-muted-foreground">({otherType})</span>
                     </div>
                  ) : (
                     <span className="text-sm">{professionalType}</span>
                  )}
               </div>
            );
         },
      },
      {
         id: 'cv_resume',
         header: 'CV/Resume',
         cell: ({ row }) => {
            const user = row.original;

            if (!user.cv_resume_url) {
               return <span className="text-muted-foreground text-sm">No CV uploaded</span>;
            }

            return (
               <div className="flex items-center gap-2">
                  <Button
                     size="sm"
                     variant="outline"
                     onClick={() => {
                        window.open(route('users.cv.view', user.id), '_blank');
                     }}
                     className="h-7"
                  >
                     <Eye className="mr-1 h-3 w-3" />
                     View
                  </Button>
                  <Button
                     size="sm"
                     variant="outline"
                     onClick={() => {
                        window.location.href = route('users.cv.download', user.id);
                     }}
                     className="h-7"
                  >
                     <Download className="mr-1 h-3 w-3" />
                     Download
                  </Button>
               </div>
            );
         },
      },
      {
         id: 'actions',
         header: () => <div className="text-end">{table.action}</div>,
         cell: ({ row }) => {
            return (
               <div className="flex justify-end gap-2 py-1">
                  <EditForm
                     user={row.original}
                     actionComponent={
                        <Button size="icon" variant="secondary" className="h-8 w-8">
                           <Pencil />
                        </Button>
                     }
                  />

                  <DeleteModal
                     routePath={route('users.destroy', row.original.id)}
                     message={table.delete_instructor_warning}
                     actionComponent={
                        <Button size="icon" variant="ghost" className="bg-destructive/8 hover:bg-destructive/6 h-8 w-8 p-0">
                           <Trash2 className="text-destructive text-sm" />
                        </Button>
                     }
                  />
               </div>
            );
         },
      },
   ];
};

export default TableColumn;
