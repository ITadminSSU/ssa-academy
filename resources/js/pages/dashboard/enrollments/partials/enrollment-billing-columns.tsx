import { Badge } from '@/components/ui/badge';
import { ColumnDef } from '@tanstack/react-table';

const formatListDate = (value?: string | null) => {
   if (!value) {
      return null;
   }

   const date = new Date(value);

   if (Number.isNaN(date.getTime())) {
      return null;
   }

   return date.toLocaleDateString('en-US', {
      month: 'long',
      day: '2-digit',
      year: 'numeric',
   });
};

const subscriptionBadgeClass = (status?: string | null) => {
   if (status === 'active' || status === 'trialing') {
      return 'bg-green-100 text-green-800 hover:bg-green-100';
   }

   if (status === 'past_due') {
      return 'bg-amber-100 text-amber-800 hover:bg-amber-100';
   }

   if (status === 'canceled' || status === 'unpaid' || status === 'paused') {
      return 'bg-red-50 text-red-700 hover:bg-red-50';
   }

   return 'bg-muted text-muted-foreground hover:bg-muted';
};

export const enrollmentBillingColumns = (
   enrollmentType: 'course' | 'exam',
   translate: LanguageTranslations,
): ColumnDef<CourseEnrollment | ExamEnrollment>[] => {
   const { table } = translate;
   const includeSubscriptionColumns = enrollmentType === 'course';

   return [
      ...(includeSubscriptionColumns
         ? [
              {
                 id: 'coupon',
                 header: table.coupon ?? 'Coupon',
                 cell: ({ row }) => {
                    const enrollment = row.original as CourseEnrollment;
                    const code = enrollment.coupon_code;

                    if (!code) {
                       return <span className="text-muted-foreground">—</span>;
                    }

                    return <span className="font-mono text-sm font-medium">{code}</span>;
                 },
              } satisfies ColumnDef<CourseEnrollment | ExamEnrollment>,
           ]
         : []),
      {
         id: 'enrolled_date',
         header: table.enrolled_date,
         cell: ({ row }) => <div>{formatListDate(row.original.entry_date) ?? '—'}</div>,
      },
      {
         id: 'expiry_date',
         header: table.expiry_date,
         cell: ({ row }) => {
            if (!row.original.expiry_date) {
               return <Badge className="bg-green-100 text-green-800 hover:bg-green-100">{table.lifetime_access}</Badge>;
            }

            return <div>{formatListDate(row.original.expiry_date)}</div>;
         },
      },
      ...(includeSubscriptionColumns
         ? [
              {
                 id: 'subscription_status',
                 header: table.subscription_status ?? 'Subscription',
                 cell: ({ row }) => {
                    const enrollment = row.original as CourseEnrollment;

                    return (
                       <Badge className={subscriptionBadgeClass(enrollment.subscription_status)}>
                          {enrollment.subscription_status_label ?? 'Not a subscription'}
                       </Badge>
                    );
                 },
              } satisfies ColumnDef<CourseEnrollment | ExamEnrollment>,
              {
                 id: 'subscription_expiry',
                 header: table.subscription_expiry ?? 'Subscription expiry',
                 cell: ({ row }) => {
                    const enrollment = row.original as CourseEnrollment;
                    const formatted = formatListDate(enrollment.subscription_expires_at);

                    return formatted ? <div>{formatted}</div> : <span className="text-muted-foreground">—</span>;
                 },
              } satisfies ColumnDef<CourseEnrollment | ExamEnrollment>,
           ]
         : []),
   ];
};
