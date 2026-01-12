import { cn } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { usePage } from '@inertiajs/react';

const AppLogo = ({ className, theme }: { theme?: 'light' | 'dark'; className?: string }) => {
   const { system } = usePage<SharedData>().props;

   // Base classes that should always be present
   const baseClasses = 'block';

   // Extract height from className if it's a custom value like h-[100px]
   const heightMatch = className?.match(/h-\[(\d+)px\]/);
   const customHeight = heightMatch ? `${heightMatch[1]}px` : null;

   // Remove height classes from className to avoid conflicts, keep only non-height classes
   const classNameWithoutHeight = className
      ? className
         .split(' ')
         .filter((cls) => !cls.startsWith('h-') && !cls.startsWith('w-'))
         .join(' ')
      : '';

   // Build final className
   const logoClassName = className
      ? cn(baseClasses, classNameWithoutHeight, 'w-auto') // Remove height from className, add w-auto
      : cn(baseClasses, 'h-6 w-auto'); // No className - use default h-6 w-auto

   // Always use inline style for custom heights to ensure they're applied
   // Add maxHeight: 'none' to override any CSS constraints
   const imgStyle = customHeight ? { height: customHeight, width: 'auto', maxHeight: 'none', maxWidth: 'none' } : undefined;

   if (theme && theme === 'dark') {
      return <img src={system.fields.logo_dark || ''} alt={system.fields.name || ''} className={logoClassName} style={imgStyle} />;
   }

   if (theme && theme === 'light') {
      return <img src={system.fields.logo_light || ''} alt={system.fields.name || ''} className={logoClassName} style={imgStyle} />;
   }

   return (
      <>
         <img src={system.fields.logo_dark || ''} alt={system.fields.name || ''} className={cn(logoClassName, 'dark:hidden')} style={imgStyle} />
         <img src={system.fields.logo_light || ''} alt={system.fields.name || ''} className={cn(logoClassName, 'hidden dark:block')} style={imgStyle} />
      </>
   );
};

export default AppLogo;
