import AppLogo from '@/components/app-logo';
import { getLogoHref } from '@/lib/dashboard';
import { SharedData } from '@/types/global';
import { Link, usePage } from '@inertiajs/react';

interface DashboardLogoLinkProps {
   className?: string;
   theme?: 'light' | 'dark';
   variant?: 'wordmark' | 'icon';
}

const DashboardLogoLink = ({ className, theme, variant = 'wordmark' }: DashboardLogoLinkProps) => {
   const { auth } = usePage<SharedData>().props;

   return (
      <Link href={getLogoHref(auth)} className="flex w-full items-center">
         <AppLogo className={className} theme={theme} variant={variant} />
      </Link>
   );
};

export default DashboardLogoLink;
