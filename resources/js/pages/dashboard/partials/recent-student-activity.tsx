import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Card } from '@/components/ui/card';
import { usePage } from '@inertiajs/react';
import { formatDistanceToNow } from 'date-fns';
import { DashboardProps } from '../index';

const RecentStudentActivity = () => {
   const { props } = usePage<DashboardProps>();
   const { recentStudentActivity, translate } = props;
   const { dashboard } = translate;

   return (
      <Card className="ssu-surface-card flex h-full flex-col p-6">
         <h3 className="mb-4 text-lg font-medium">{dashboard.recent_student_activity ?? 'Recent Student Activity'}</h3>

         {recentStudentActivity.length > 0 ? (
            <div className="space-y-4">
               {recentStudentActivity.map((activity, index) => {
                  const initials = activity.user_name
                     .split(' ')
                     .map((part) => part[0])
                     .join('')
                     .slice(0, 2)
                     .toUpperCase();

                  return (
                     <div key={`${activity.user_name}-${activity.occurred_at}-${index}`} className="flex items-start gap-3">
                        <Avatar className="h-10 w-10">
                           {activity.user_photo ? (
                              <AvatarImage src={activity.user_photo} alt={activity.user_name} />
                           ) : (
                              <AvatarFallback>{initials}</AvatarFallback>
                           )}
                        </Avatar>

                        <div className="min-w-0 flex-1">
                           <p className="text-sm leading-snug">
                              <span className="font-semibold">{activity.user_name}</span>{' '}
                              <span className="text-muted-foreground">{activity.action.toLowerCase()}</span>
                              {activity.detail ? (
                                 <>
                                    {' '}
                                    <span className="font-medium">{activity.detail}</span>
                                 </>
                              ) : null}
                           </p>
                           <p className="text-muted-foreground mt-0.5 text-xs">
                              {formatDistanceToNow(new Date(activity.occurred_at), { addSuffix: true })}
                           </p>
                        </div>
                     </div>
                  );
               })}
            </div>
         ) : (
            <div className="text-muted-foreground flex flex-1 items-center justify-center text-sm">
               {dashboard.recent_student_activity_empty ?? 'No recent learner activity yet.'}
            </div>
         )}
      </Card>
   );
};

export default RecentStudentActivity;
