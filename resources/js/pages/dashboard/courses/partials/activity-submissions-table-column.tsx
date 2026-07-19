import { Badge } from '@/components/ui/badge';
import { ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';
import GradeActivityDialog from './grade-activity-dialog';

const ActivitySubmissionsTableColumn = (translate: LanguageTranslations): ColumnDef<LessonActivitySubmission>[] => {
   return [
      {
         accessorKey: 'student',
         header: 'Learner',
         cell: ({ row }) => {
            const student = row.original.student;
            return (
               <div className="py-1">
                  <p className="font-medium">{student?.name || 'N/A'}</p>
                  <p className="text-muted-foreground text-xs">{student?.email || ''}</p>
               </div>
            );
         },
      },
      {
         accessorKey: 'lesson',
         header: 'Activity',
         cell: ({ row }) => <p className="font-medium">{row.original.lesson?.title || 'N/A'}</p>,
      },
      {
         accessorKey: 'submitted_at',
         header: 'Submitted',
         cell: ({ row }) => {
            const date = row.getValue('submitted_at') as string;
            return (
               <div className="py-1 text-sm">
                  <p>{format(new Date(date), 'MMM dd, yyyy')}</p>
                  <p className="text-muted-foreground text-xs">{format(new Date(date), 'hh:mm a')}</p>
               </div>
            );
         },
      },
      {
         accessorKey: 'status',
         header: () => <div className="text-center">Status</div>,
         cell: ({ row }) => {
            const status = row.getValue('status') as string;
            const variant = ['passed', 'approved', 'graded'].includes(status) ? 'default' : 'secondary';
            return (
               <div className="text-center">
                  <Badge variant={variant}>{status}</Badge>
               </div>
            );
         },
      },
      {
         accessorKey: 'marks_obtained',
         header: () => <div className="text-center">Marks</div>,
         cell: ({ row }) => {
            const submission = row.original;
            const total = submission.lesson?.activity_total_mark || 0;
            return (
               <div className="text-center text-sm">
                  {submission.marks_obtained !== null && submission.marks_obtained !== undefined
                     ? `${submission.marks_obtained} / ${total}`
                     : '—'}
               </div>
            );
         },
      },
      {
         id: 'actions',
         header: () => <div className="text-right">{translate.table.action}</div>,
         cell: ({ row }) => (
            <div className="text-right">
               <GradeActivityDialog submission={row.original} />
            </div>
         ),
      },
   ];
};

export default ActivitySubmissionsTableColumn;
