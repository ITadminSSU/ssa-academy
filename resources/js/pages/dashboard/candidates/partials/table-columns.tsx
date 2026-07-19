import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ColumnDef } from '@tanstack/react-table';
import { Link } from '@inertiajs/react';
import { ArrowUpDown, Eye } from 'lucide-react';

export type CandidateStatus = 'new' | 'in_review' | 'shortlisted' | 'hired' | 'rejected';

const statusVariant: Record<CandidateStatus, 'default' | 'secondary' | 'outline' | 'destructive'> = {
   new: 'secondary',
   in_review: 'outline',
   shortlisted: 'default',
   hired: 'default',
   rejected: 'destructive',
};

const statusLabel: Record<CandidateStatus, string> = {
   new: 'New',
   in_review: 'In Review',
   shortlisted: 'Shortlisted',
   hired: 'Hired',
   rejected: 'Rejected',
};

const TableColumns = (translate: LanguageTranslations): ColumnDef<User>[] => {
   const { table, dashboard } = translate;

   return [
      {
         accessorKey: 'name',
         header: ({ column }) => (
            <Button variant="ghost" className="p-0 hover:bg-transparent" onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}>
               {table.name}
               <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
         ),
         cell: ({ row }) => (
            <div>
               <p className="font-medium">{row.original.name}</p>
               <p className="text-muted-foreground text-xs">{row.original.email}</p>
            </div>
         ),
      },
      {
         accessorKey: 'professional_type',
         header: dashboard.professional_type ?? 'Professional Type',
         cell: ({ row }) => (
            <span className="text-sm">
               {row.original.professional_type?.name ?? row.original.professional_type_other ?? '—'}
            </span>
         ),
      },
      {
         accessorKey: 'candidate_status',
         header: dashboard.candidate_status ?? 'Status',
         cell: ({ row }) => {
            const status = (row.original.candidate_status ?? 'new') as CandidateStatus;

            return <Badge variant={statusVariant[status]}>{statusLabel[status]}</Badge>;
         },
      },
      {
         accessorKey: 'has_cv',
         header: dashboard.cv_resume ?? 'CV',
         cell: ({ row }) =>
            row.original.has_cv ? (
               <a href={route('users.cv.view', { id: row.original.id })} target="_blank" rel="noreferrer" className="text-primary text-sm hover:underline">
                  {dashboard.view_cv ?? 'View CV'}
               </a>
            ) : (
               <span className="text-muted-foreground text-sm">—</span>
            ),
      },
      {
         accessorKey: 'paid_course_count',
         header: dashboard.paid_courses ?? 'Paid Courses',
         cell: ({ row }) => <span className="text-sm">{row.original.paid_course_count ?? 0}</span>,
      },
      {
         accessorKey: 'paid_exam_count',
         header: dashboard.paid_exams ?? 'Paid Exams',
         cell: ({ row }) => <span className="text-sm">{row.original.paid_exam_count ?? 0}</span>,
      },
      {
         id: 'actions',
         header: table.action ?? 'Action',
         cell: ({ row }) => (
            <Button variant="outline" size="sm" asChild>
               <Link href={route('candidates.show', { id: row.original.id })}>
                  <Eye className="mr-1 h-4 w-4" />
                  {dashboard.view_profile ?? 'View'}
               </Link>
            </Button>
         ),
      },
   ];
};

export default TableColumns;
