import { SharedData } from '@/types/global';
import { Link, usePage } from '@inertiajs/react';

const ProfileSetupBanner = ({ onProfile = false }: { onProfile?: boolean }) => {
   const { auth } = usePage<SharedData>().props;

   if (!auth.profileSetupRequired) {
      return null;
   }

   const href = onProfile ? '#profile-questions' : auth.profileUrl;

   if (!href) {
      return null;
   }

   return (
      <div className="border-border bg-muted/40 flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between">
         <p className="text-sm">
            Finish setting up your profile so we have your background on file. You can keep browsing and enrolling while this is open.
         </p>
         {onProfile ? (
            <a href={href} className="text-sm font-medium underline">
               Complete the questions below
            </a>
         ) : (
            <Link href={href} className="text-sm font-medium underline">
               Go to profile
            </Link>
         )}
      </div>
   );
};

export default ProfileSetupBanner;
