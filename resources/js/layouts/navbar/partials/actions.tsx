import Appearance from '@/components/appearance';
import Language from '@/components/language';
import Notification from '@/components/notification';
import ProfileToggle from '@/components/profile-toggle';
import { Button } from '@/components/ui/button';
import { useAuth } from '@/hooks/use-auth';
import useScreen from '@/hooks/use-screen';
import { SharedData } from '@/types/global';
import { Link, usePage } from '@inertiajs/react';

const Actions = ({ language }: { language: boolean }) => {
   const { props } = usePage<SharedData>();
   const { navbar, translate, system } = props;
   const { isLoggedIn } = useAuth();
   const { screen } = useScreen();
   const sortedItems = navbar.navbar_items.sort((a, b) => a.sort - b.sort);

   const actionElements = () =>
      sortedItems.map((item) => {
         if (item.slug === 'theme') {
            return <Appearance key={item.id} />;
         } else if (system.fields.language_selector && language && item.slug === 'language') {
            return <Language key={item.id} />;
         } else if (isLoggedIn && item.slug === 'notification') {
            return <Notification key={item.id} />;
         } else {
            return null;
         }
      });

   return (
      <div className="flex items-center gap-2">
         {/* Inline theme/language/notifications from tablet up; no mobile List button */}
         {screen > 768 ? (
            <div className="flex items-center gap-2">{actionElements()}</div>
         ) : (
            isLoggedIn && <Notification />
         )}

         {isLoggedIn ? (
            sortedItems.map((item) => {
               if (item.slug === 'profile') {
                  return <ProfileToggle key={item.id} />;
               } else {
                  return null;
               }
            })
         ) : (
            <div className="hidden space-x-2 sm:block">
               <Button asChild variant="outline" className="">
                  <Link href={route('register')}>{translate.button.sign_up}</Link>
               </Button>
               <Button asChild variant="brand" className="">
                  <Link href={route('login')}>{translate.button.log_in}</Link>
               </Button>
            </div>
         )}
      </div>
   );
};

export default Actions;
