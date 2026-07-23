import DeleteModal from '@/components/inertia/delete-modal';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { ColumnDef } from '@tanstack/react-table';
import { ArrowUpDown, Download, ExternalLink, Eye, Pencil, Trash2 } from 'lucide-react';
import { Link } from '@inertiajs/react';
import EditForm from './edit-form';

const roleLabel = (role: string) => {
   switch (role) {
      case 'admin':
         return 'Admin';
      case 'instructor':
         return 'Trainer';
      case 'student':
         return 'Student';
      default:
         return role;
   }
};

const learnerTypeLabel = (user: User, common: LanguageTranslations['common']) => {
   if (user.role !== 'student') {
      return '—';
   }

   return user.user_type === 'employee' ? common.employee : common.external;
};

const roleBadgeClass = (role: string) => {
   switch (role) {
      case 'admin':
         return 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-200';
      case 'instructor':
         return 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200';
      default:
         return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200';
   }
};

const TableColumn = (translate: LanguageTranslations, protectedUserId?: number | null): ColumnDef<User>[] => {
   const { table, common, dashboard } = translate;

   const text = (value: string | undefined, fallback: string) => value?.trim() || fallback;

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
                  <div className="mb-0.5 flex items-center gap-2">
                     <p className="text-base font-medium">{row.original.name}</p>
                     {protectedUserId && row.original.id === protectedUserId && (
                        <Badge variant="secondary" className="text-xs">
                           {text(dashboard.primary_admin_badge, 'Primary admin')}
                        </Badge>
                     )}
                  </div>
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
            <Badge variant="secondary" className={roleBadgeClass(row.original.role)}>
               {roleLabel(row.original.role)}
            </Badge>
         ),
      },
      {
         accessorKey: 'user_type',
         header: table.user_type,
         cell: ({ row }) => <span>{learnerTypeLabel(row.original, common)}</span>,
      },
      {
         id: 'professional_type',
         header: text(dashboard.professional_type, 'Professional Type'),
         cell: ({ row }) => {
            if (row.original.role !== 'student') {
               return <span className="text-muted-foreground text-sm">—</span>;
            }

            const user = row.original;
            const professionalType = user.professional_type?.name || 'N/A';
            const otherType = user.professional_type_other;

            return (
               <div>
                  {professionalType === 'Other' && otherType ? (
                     <div>
                        <span className="text-sm">{professionalType}</span>
                        <br />
                        <span className="text-muted-foreground text-xs">({otherType})</span>
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
         header: text(dashboard.cv_resume, 'CV/Resume'),
         cell: ({ row }) => {
            if (row.original.role !== 'student') {
               return <span className="text-muted-foreground text-sm">—</span>;
            }

            const user = row.original;

            if (!user.cv_resume_url) {
               return <span className="text-muted-foreground text-sm">{text(dashboard.no_cv_uploaded, 'No CV uploaded')}</span>;
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
            const user = row.original;
            const isProtected = protectedUserId != null && user.id === protectedUserId;
            const canEdit = ['student', 'admin', 'instructor'].includes(user.role);
            const canDelete = !isProtected && (user.role === 'student' || user.role === 'admin');
            const trainerProfileId = user.instructor_id ?? user.instructor?.id;

            if (!canEdit && !canDelete && user.role !== 'instructor') {
               return <div className="text-muted-foreground py-1 text-end text-sm">—</div>;
            }

            return (
               <TooltipProvider>
                  <div className="flex justify-end gap-2 py-1">
                     {canEdit && (
                        <Tooltip>
                           <TooltipTrigger asChild>
                              <span>
                                 <EditForm
                                    user={user}
                                    protectedUserId={protectedUserId}
                                    actionComponent={
                                       <Button size="icon" variant="secondary" className="h-8 w-8">
                                          <Pencil />
                                       </Button>
                                    }
                                 />
                              </span>
                           </TooltipTrigger>
                           <TooltipContent>{text(dashboard.edit_user, 'Edit user')}</TooltipContent>
                        </Tooltip>
                     )}

                     {user.role === 'instructor' && trainerProfileId && (
                        <Tooltip>
                           <TooltipTrigger asChild>
                              <Button size="icon" variant="outline" className="h-8 w-8" asChild>
                                 <Link href={route('instructors.show', trainerProfileId)} target="_blank">
                                    <ExternalLink className="h-4 w-4" />
                                 </Link>
                              </Button>
                           </TooltipTrigger>
                           <TooltipContent>{text(dashboard.view_trainer_profile, 'View trainer profile')}</TooltipContent>
                        </Tooltip>
                     )}

                     {user.role === 'instructor' && (
                        <Tooltip>
                           <TooltipTrigger asChild>
                              <Button size="icon" variant="outline" className="h-8 w-8" asChild>
                                 <Link href={route('instructors.index')}>
                                    <Eye className="h-4 w-4" />
                                 </Link>
                              </Button>
                           </TooltipTrigger>
                           <TooltipContent>{text(dashboard.manage_trainers, 'Manage trainers')}</TooltipContent>
                        </Tooltip>
                     )}

                     {canDelete && (
                        <Tooltip>
                           <TooltipTrigger asChild>
                              <span>
                                 <DeleteModal
                                    routePath={route('users.destroy', user.id)}
                                    message={table.delete_instructor_warning}
                                    actionComponent={
                                       <Button size="icon" variant="ghost" className="bg-destructive/8 hover:bg-destructive/6 h-8 w-8 p-0">
                                          <Trash2 className="text-destructive text-sm" />
                                       </Button>
                                    }
                                 />
                              </span>
                           </TooltipTrigger>
                           <TooltipContent>{text(dashboard.delete_user, 'Delete user')}</TooltipContent>
                        </Tooltip>
                     )}
                  </div>
               </TooltipProvider>
            );
         },
      },
   ];
};

export default TableColumn;
