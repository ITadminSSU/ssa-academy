import AppLogo from '@/components/app-logo';
import { getLogoHref } from '@/lib/dashboard';
import { type LogoPlacement } from '@/lib/logo-placements';
import { SharedData } from '@/types/global';
import { Link, usePage } from '@inertiajs/react';

interface DashboardLogoLinkProps {
   className?: string;
   theme?: 'light' | 'dark';
   variant?: 'wordmark' | 'icon';
   placement?: LogoPlacement;
}

const DashboardLogoLink = ({ className, theme, variant = 'wordmark', placement }: DashboardLogoLinkProps) => {
   const { auth } = usePage<SharedData>().props;

   return (
      <Link href={getLogoHref(auth)} className="flex w-full items-center">
         <AppLogo className={className} theme={theme} variant={variant} placement={placement} />
      </Link>
   );
};

export default DashboardLogoLink;
