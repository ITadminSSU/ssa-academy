import { resolveLogo, resolveSiteName } from '@/lib/branding';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { usePage } from '@inertiajs/react';

const AppLogo = ({ className, theme, variant = 'wordmark' }: { theme?: 'light' | 'dark'; className?: string; variant?: 'wordmark' | 'icon' }) => {
   const { system, branding } = usePage<SharedData>().props;
   const siteName = resolveSiteName(system?.fields?.name);

   const pixelHeightMatch = className?.match(/\bh-\[(\d+)px\]\b/);
   const customHeight = pixelHeightMatch ? `${pixelHeightMatch[1]}px` : null;
   const resolvedClassName = customHeight ? className?.replace(/\bh-\[\d+px\]\b/, '').trim() : className;

   const logoClassName =
      variant === 'icon'
         ? resolvedClassName
            ? cn('block object-contain', resolvedClassName)
            : cn('block h-8 w-8 object-contain')
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

   if (theme === 'dark') {
      return logoLight ? <img src={logoLight} alt={siteName} className={logoClassName} style={imgStyle} /> : renderPlaceholder();
   }

   if (theme === 'light') {
      return logoDark ? <img src={logoDark} alt={siteName} className={logoClassName} style={imgStyle} /> : renderPlaceholder();
   }

   if (!logoDark && !logoLight) {
      return renderPlaceholder();
   }

   return (
      <>
         {logoDark ? (
            <img src={logoDark} alt={siteName} className={cn(logoClassName, 'dark:hidden')} style={imgStyle} />
         ) : (
            <div className={cn(placeholderClassName, 'dark:hidden')} style={placeholderStyle}>
               <span className="text-primary line-clamp-2 leading-tight">{siteName}</span>
            </div>
         )}
         {logoLight ? (
            <img src={logoLight} alt={siteName} className={cn(logoClassName, 'hidden dark:block')} style={imgStyle} />
         ) : (
            <div className={cn(placeholderClassName, 'hidden dark:block')} style={placeholderStyle}>
               <span className="text-primary line-clamp-2 leading-tight">{siteName}</span>
            </div>
         )}
      </>
   );
};

export default AppLogo;
