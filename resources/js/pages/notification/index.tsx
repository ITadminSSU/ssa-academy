import TableFooter from '@/components/table/table-footer';
import TablePageSize from '@/components/table/table-page-size';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { Link, router, usePage } from '@inertiajs/react';
import { format, formatDistanceToNow } from 'date-fns';
import { Bell } from 'lucide-react';
import { ReactNode } from 'react';
import Layout from './layout';

interface Props extends SharedData {
   notifications: Pagination<Notification>;
}

const Index = () => {
   const { notifications, translate } = usePage<Props>().props;
   const { button, frontend, dashboard } = translate;

   return (
      <div className="space-y-6">
         <div className="ssu-catalog-hero flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
               <p className="ssu-kicker mb-1">Account</p>
               <h1 className="font-display text-2xl font-bold sm:text-3xl">{dashboard.notifications}</h1>
               <p className="mt-2 max-w-2xl text-sm opacity-80">{frontend.notifications_page_description}</p>
            </div>

            <div className="flex shrink-0 items-center gap-2">
               {notifications.total > 0 && (
                  <Button variant="outline" size="sm" onClick={() => router.put(route('notifications.mark-all-as-read'))}>
                     {button.mark_all_as_read}
                  </Button>
               )}
               <TablePageSize
                  pageData={notifications}
                  dropdownList={[10, 15, 20, 25]}
                  routeName="notifications.index"
                  className="h-9"
               />
            </div>
         </div>

         {notifications.data.length > 0 ? (
            <>
               <Card className="ssu-surface-card overflow-hidden p-0">
                  <div className="divide-y">
                     {notifications.data.map(({ id, data, created_at, read_at }) => {
                        const time = formatDistanceToNow(new Date(created_at), { addSuffix: true });
                        const timeText = time.slice(0, 1).toUpperCase() + time.slice(1);

                        return (
                           <Link
                              key={id}
                              href={route('notifications.show', id)}
                              className={cn(
                                 'hover:bg-accent/50 flex items-start gap-4 px-5 py-4 transition-colors',
                                 !read_at && 'bg-primary/5',
                              )}
                           >
                              <div className="bg-primary/10 text-primary flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                                 <Bell className="h-4 w-4" />
                              </div>

                              <div className="min-w-0 flex-1">
                                 <p className="text-sm font-medium capitalize">{data.title}</p>
                                 <span className="text-muted-foreground text-xs" title={format(new Date(created_at), 'PPpp')}>
                                    {timeText}
                                 </span>
                              </div>

                              {!read_at && <span className="bg-primary mt-2 h-2 w-2 shrink-0 rounded-full" aria-hidden="true" />}
                           </Link>
                        );
                     })}
                  </div>
               </Card>

               <TableFooter className="ssu-surface-card p-5 sm:p-7" routeName="notifications.index" paginationInfo={notifications} />
            </>
         ) : (
            <Card className="ssu-surface-card border">
               <CardContent className="flex flex-col items-center justify-center gap-3 p-12 text-center">
                  <Bell className="text-muted-foreground h-10 w-10" />
                  <p className="text-muted-foreground text-sm">{frontend.no_notifications}</p>
               </CardContent>
            </Card>
         )}
      </div>
   );
};

Index.layout = (page: ReactNode) => <Layout children={page} />;

export default Index;
