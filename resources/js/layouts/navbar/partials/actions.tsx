import ProfileToggle from '@/components/profile-toggle';
import { useAuth } from '@/hooks/use-auth';
import { SharedData } from '@/types/global';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

const Actions = ({ variant = 'default' }: { language: boolean; variant?: 'default' | 'public' }) => {
   const { props } = usePage<SharedData>();
   const { navbar } = props;
   const { isLoggedIn } = useAuth();
   const sortedItems = navbar.navbar_items.sort((a, b) => a.sort - b.sort);

   if (isLoggedIn) {
      return (
         <div className="flex items-center gap-2">
            {sortedItems.map((item) => (item.slug === 'profile' ? <ProfileToggle key={item.id} /> : null))}
         </div>
      );
   }

   if (variant === 'public') {
      return (
         <div className="flex items-center gap-3">
            <Link href={route('login')} className="ssu-pub-login hidden sm:inline">
               Log in
            </Link>
            <Link href={route('register')} className="ssu-pub-cta">
               Get started
               <ArrowRight className="h-3.5 w-3.5" />
            </Link>
         </div>
      );
   }

   return (
      <div className="hidden space-x-2 sm:block">
         <Link href={route('register')} className="ssu-pub-login">
            Sign up
         </Link>
         <Link href={route('login')} className="ssu-pub-cta">
            Log in
         </Link>
      </div>
   );
};

export default Actions;
