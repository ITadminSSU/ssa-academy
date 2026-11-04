import { Badge } from '@/components/ui/badge';
import { ColumnDef } from '@tanstack/react-table';

export interface ActiveSubscriptionRow {
   student_name: string;
   email: string;
   course: string;
   stripe_status_label: string;
   period_end: string;
   cancel_at_period_end: boolean;
   cancel_label: string;
}

const textOrDash = (value?: string) => (value ? <span>{value}</span> : <span className="text-muted-foreground">—</span>);

const ActiveSubscriptionColumns = (): ColumnDef<ActiveSubscriptionRow>[] => [
   {
      id: 'student_name',
      header: 'Student name',
      cell: ({ row }) => textOrDash(row.original.student_name),
   },
   {
      id: 'email',
      header: 'Email',
      cell: ({ row }) => textOrDash(row.original.email),
   },
   {
      id: 'course',
      header: 'Course',
      cell: ({ row }) => textOrDash(row.original.course),
   },
   {
      id: 'stripe_status',
      header: 'Stripe status',
      cell: ({ row }) => <Badge className="bg-green-100 text-green-800 hover:bg-green-100">{row.original.stripe_status_label}</Badge>,
   },
   {
      id: 'period_end',
      header: 'Current period ends',
      cell: ({ row }) => textOrDash(row.original.period_end),
   },
   {
      id: 'cancel',
      header: 'Cancels at period end',
      cell: ({ row }) => (
         <Badge
            className={
               row.original.cancel_at_period_end
                  ? 'bg-amber-100 text-amber-800 hover:bg-amber-100'
                  : 'bg-muted text-muted-foreground hover:bg-muted'
            }
         >
            {row.original.cancel_label}
         </Badge>
      ),
   },
];

export default ActiveSubscriptionColumns;
