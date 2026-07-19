import { Button, ButtonProps } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface Props extends ButtonProps {
   children: React.ReactNode;
   containerClass?: string;
   shadow?: boolean;
   shadowClass?: string;
}

const ButtonGradientPrimary = ({ children, className, shadow = false, shadowClass, containerClass, ...props }: Props) => {
   return (
      <div className={cn('relative', containerClass)}>
         {shadow && (
            <div
               className={cn(
                  "after:pointer-events-none after:absolute after:top-1/2 after:-left-4 after:h-16 after:w-16 after:-translate-y-1/2 after:rounded-full after:bg-primary/20 after:blur-2xl after:content-['']",
                  shadowClass,
               )}
            ></div>
         )}

         <Button
            className={cn(
               'from-[oklch(0.64_0.13_250)] to-primary hover:from-primary hover:to-primary-dark font-display relative z-10 h-auto bg-gradient-to-r px-5 py-2.5 text-white shadow-sm transition-all hover:shadow-md',
               className,
            )}
            {...props}
         >
            {children}
         </Button>
      </div>
   );
};

export default ButtonGradientPrimary;
