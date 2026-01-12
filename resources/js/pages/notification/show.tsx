import { Button } from '@/components/ui/button';
import LandingLayout from '@/layouts/landing-layout';
import { Link } from '@inertiajs/react';
import { ReactNode } from 'react';
import { Renderer } from 'richtor';
import 'richtor/styles';

const Show = ({ notification, translate }: { notification: Notification; translate: any }) => {
   const { common } = translate;

   return (
      <div className="container mx-auto max-w-2xl py-12">
         <p className="font-medium capitalize">{notification.data.title}</p>

         <Renderer value={notification.data.body} />

         {notification.data.url && (
            <Link href={notification.data.url}>
               <Button>{common.view}</Button>
            </Link>
         )}
      </div>
   );
};

Show.layout = (page: ReactNode) => <LandingLayout children={page} customizable={false} />;

export default Show;
