import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { SharedData } from '@/types/global';
import { Link, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { ArrowLeft } from 'lucide-react';
import { ReactNode } from 'react';
import { Renderer } from 'richtor';
import 'richtor/styles';
import Layout from './layout';

interface Props extends SharedData {
   notification: Notification;
}

const Show = () => {
   const { notification, translate } = usePage<Props>().props;
   const { common, button } = translate;

   return (
      <div className="space-y-6">
         <Button asChild variant="ghost" size="sm" className="-ml-2">
            <Link href={route('notifications.index')}>
               <ArrowLeft className="mr-2 h-4 w-4" />
               {button.back}
            </Link>
         </Button>

         <div>
            <p className="ssu-kicker mb-1">Account</p>
            <h1 className="font-display text-2xl font-bold capitalize sm:text-3xl">{notification.data.title}</h1>
            <p className="text-muted-foreground mt-2 text-sm">{format(new Date(notification.created_at), 'PPpp')}</p>
         </div>

         <Card className="ssu-surface-card">
            <CardContent className="space-y-6 p-6">
               <Renderer value={notification.data.body} />

               {notification.data.url && (
                  <Button asChild>
                     <Link href={notification.data.url}>{common.view}</Link>
                  </Button>
               )}
            </CardContent>
         </Card>
      </div>
   );
};

Show.layout = (page: ReactNode) => <Layout children={page} />;

export default Show;
