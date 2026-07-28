import { mergeLogoSizes } from '@/lib/logo-placements';
import { SharedData } from '@/types/global';
import { usePage } from '@inertiajs/react';

const LogoSizeStyles = () => {
   const { system } = usePage<SharedData>().props;
   const sizes = mergeLogoSizes(system?.fields?.logo_sizes);

   return (
      <style>
         {`:root {
   --ssu-logo-nav-height: ${sizes.navbar.height}px;
   --ssu-logo-nav-max-width: ${sizes.navbar.maxWidth}px;
   --ssu-logo-footer-height: ${sizes.footer.height}px;
   --ssu-logo-footer-max-width: ${sizes.footer.maxWidth}px;
   --ssu-logo-auth-height: ${sizes.auth.height}px;
   --ssu-logo-auth-max-width: ${sizes.auth.maxWidth}px;
   --ssu-logo-dashboard-height: ${sizes.dashboard.height}px;
   --ssu-logo-dashboard-max-width: ${sizes.dashboard.maxWidth}px;
   --ssu-logo-certificate-height: ${sizes.certificate.height}px;
   --ssu-logo-certificate-max-width: ${sizes.certificate.maxWidth}px;
}`}
      </style>
   );
};

export default LogoSizeStyles;
