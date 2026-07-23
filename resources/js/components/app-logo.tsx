import { resolveLogo, resolveSiteName } from '@/lib/branding';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { usePage } from '@inertiajs/react';

const stripHeightClasses = (className?: string) => className?.replace(/\bh-(?:\[[^\]]+\]|\S+)/g, '').trim();

const normalizeLogoUrl = (url?: string | null) => (url ? url.split('?')[0] : '');

const AppLogo = ({ className, theme, variant = 'wordmark' }: { theme?: 'light' | 'dark'; className?: string; variant?: 'wordmark' | 'icon' }) => {
   const { system, branding } = usePage<SharedData>().props;
   const siteName = resolveSiteName(system?.fields?.name);

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

   const imgStyle = customHeight ? { height: customHeight, width: 'auto', maxHeight: customHeight } : undefined;

   const placeholderClassName = resolvedClassName
      ? cn(
           'bg-primary/5 flex items-center justify-center rounded-lg border border-primary/15 px-3 py-2 text-center text-sm font-semibold tracking-tight',
           resolvedClassName,
        )
      : cn('bg-primary/5 flex h-8 items-center justify-center rounded-lg border border-primary/15 px-2 text-sm font-semibold tracking-tight');

   const placeholderStyle = customHeight ? { height: customHeight, minWidth: customHeight } : undefined;

   const renderPlaceholder = () => (
      <div className={placeholderClassName} style={placeholderStyle}>
         <span className="text-primary line-clamp-2 leading-tight">{siteName}</span>
      </div>
   );

   const logoDark = variant === 'icon' ? resolveLogo(branding?.logos?.icon || system?.fields?.logo_dark, 'icon') : resolveLogo(system?.fields?.logo_dark, 'dark');
   const logoLight = variant === 'icon' ? resolveLogo(branding?.logos?.icon || system?.fields?.logo_light, 'icon') : resolveLogo(system?.fields?.logo_light, 'light');
   const usesSameLogo = Boolean(logoDark && logoLight && normalizeLogoUrl(logoDark) === normalizeLogoUrl(logoLight));

   const renderLogoImage = (src: string, visibilityClassName?: string) => (
      <img src={src} alt={siteName} className={cn(logoClassName, visibilityClassName)} style={imgStyle} />
   );

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
