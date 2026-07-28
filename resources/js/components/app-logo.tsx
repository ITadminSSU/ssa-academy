import { resolveLogo, resolveSiteName } from '@/lib/branding';
import { mergeLogoSizes, type LogoPlacement } from '@/lib/logo-placements';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { usePage } from '@inertiajs/react';
import { CSSProperties } from 'react';

const stripHeightClasses = (className?: string) => className?.replace(/\bh-(?:\[[^\]]+\]|\S+)/g, '').trim();

const normalizeLogoUrl = (url?: string | null) => (url ? url.split('?')[0] : '');

const placementClassMap: Record<LogoPlacement, string> = {
   navbar: 'ssu-nav-logo',
   footer: 'ssu-footer-logo',
   auth: 'ssu-auth-logo-colored',
   dashboard: 'dashboard-sidebar-logo',
   certificate: 'ssu-certificate-logo',
};

const AppLogo = ({
   className,
   theme,
   placement,
   variant = 'wordmark',
}: {
   theme?: 'light' | 'dark';
   placement?: LogoPlacement;
   className?: string;
   variant?: 'wordmark' | 'icon' | 'footer';
}) => {
   const { system, branding } = usePage<SharedData>().props;
   const siteName = resolveSiteName(system?.fields?.name);
   const logoSizes = mergeLogoSizes(system?.fields?.logo_sizes);

   const resolvedPlacement: LogoPlacement | null =
      placement ?? (variant === 'footer' ? 'footer' : className?.includes('ssu-auth-logo') ? 'auth' : className?.includes('dashboard-sidebar-logo') ? 'dashboard' : className?.includes('ssu-nav-logo') ? 'navbar' : null);

   const placementSize = resolvedPlacement ? logoSizes[resolvedPlacement] : null;

   const pixelHeightMatch = className?.match(/\bh-\[(\d+)px\]\b/);
   const customHeight = pixelHeightMatch ? `${pixelHeightMatch[1]}px` : null;
   const resolvedClassName = customHeight ? stripHeightClasses(className) : className;
   const isFramedLogo = Boolean(resolvedClassName?.includes('ssu-nav-logo') || resolvedClassName?.includes('ssu-footer-logo'));

   const logoClassName =
      variant === 'icon'
         ? resolvedClassName
            ? cn('block object-contain', resolvedClassName)
            : cn('block h-8 w-8 object-contain')
         : isFramedLogo
           ? cn('block h-full w-auto max-w-full object-contain object-left', stripHeightClasses(resolvedClassName))
           : resolvedClassName
             ? cn('block object-contain', resolvedClassName)
             : cn('block h-8 w-auto max-w-[220px] object-contain');

   const sizeStyle: CSSProperties | undefined = placementSize
      ? {
           height: `${placementSize.height}px`,
           maxHeight: `${placementSize.height}px`,
           maxWidth: `${placementSize.maxWidth}px`,
           width: 'auto',
        }
      : customHeight
        ? { height: customHeight, width: 'auto', maxHeight: customHeight }
        : undefined;

   const placeholderClassName = resolvedClassName
      ? cn(
           'bg-primary/5 flex items-center justify-center rounded-lg border border-primary/15 px-3 py-2 text-center text-sm font-semibold tracking-tight',
           resolvedClassName,
        )
      : cn('bg-primary/5 flex h-8 items-center justify-center rounded-lg border border-primary/15 px-2 text-sm font-semibold tracking-tight');

   const placeholderStyle = sizeStyle ?? (customHeight ? { height: customHeight, minWidth: customHeight } : undefined);

   const renderPlaceholder = () => (
      <div className={placeholderClassName} style={placeholderStyle}>
         <span className="text-primary line-clamp-2 leading-tight">{siteName}</span>
      </div>
   );

   const fields = system?.fields;
   const logoDark = variant === 'icon' ? resolveLogo(branding?.logos?.icon || fields?.logo_dark, 'icon') : resolveLogo(fields?.logo_dark, 'dark');
   const logoLight = variant === 'icon' ? resolveLogo(branding?.logos?.icon || fields?.logo_light, 'icon') : resolveLogo(fields?.logo_light, 'light');
   const logoNavbar = resolveLogo(fields?.logo_navbar || fields?.logo_dark, 'dark');
   const logoFooter = resolveLogo(fields?.logo_footer || branding?.logos?.footer, 'footer');
   const logoAuth = resolveLogo(fields?.logo_auth || fields?.logo_light, 'light');
   const logoDashboard = resolveLogo(fields?.logo_dashboard || fields?.logo_light, 'light');
   const logoCertificate = resolveLogo(fields?.logo_certificate, 'certificate');
   const usesSameLogo = Boolean(logoDark && logoLight && normalizeLogoUrl(logoDark) === normalizeLogoUrl(logoLight));

   const placementLogoMap: Record<LogoPlacement, string | undefined> = {
      navbar: logoNavbar,
      footer: logoFooter || logoNavbar,
      auth: logoAuth,
      dashboard: logoDashboard,
      certificate: logoCertificate,
   };

   const renderLogoImage = (src: string, visibilityClassName?: string) => (
      <img src={src} alt={siteName} className={cn(logoClassName, visibilityClassName)} style={sizeStyle} />
   );

   if (resolvedPlacement) {
      const src = placementLogoMap[resolvedPlacement];
      return src ? renderLogoImage(src) : renderPlaceholder();
   }

   if (variant === 'footer') {
      const src = logoFooter || logoLight || logoDark;
      return src ? renderLogoImage(src) : renderPlaceholder();
   }

   if (theme === 'dark') {
      return logoLight ? renderLogoImage(logoLight) : renderPlaceholder();
   }

   if (theme === 'light') {
      return logoDark ? renderLogoImage(logoDark) : renderPlaceholder();
   }

   if (!logoDark && !logoLight) {
      return renderPlaceholder();
   }

   if (usesSameLogo) {
      return renderLogoImage(logoDark || logoLight!);
   }

   return (
      <>
         {logoDark ? (
            renderLogoImage(logoDark, 'dark:hidden')
         ) : (
            <div className={cn(placeholderClassName, 'dark:hidden')} style={placeholderStyle}>
               <span className="text-primary line-clamp-2 leading-tight">{siteName}</span>
            </div>
         )}
         {logoLight ? (
            renderLogoImage(logoLight, 'hidden dark:block')
         ) : (
            <div className={cn(placeholderClassName, 'hidden dark:block')} style={placeholderStyle}>
               <span className="text-primary line-clamp-2 leading-tight">{siteName}</span>
            </div>
         )}
      </>
   );
};

export default AppLogo;
