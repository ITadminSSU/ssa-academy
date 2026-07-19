import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { Grid, List } from 'lucide-react';

interface CourseCatalogToolbarProps {
   title: string;
   description?: string | null;
   viewType: string;
   onViewChange: (view: 'grid' | 'list') => void;
   gridLabel: string;
   listLabel: string;
   kicker?: string;
   variant?: 'hero' | 'plain';
}

const CourseCatalogToolbar = ({
   title,
   description,
   viewType,
   onViewChange,
   gridLabel,
   listLabel,
   kicker = 'Course catalog',
   variant = 'plain',
}: CourseCatalogToolbarProps) => {
   const heroOutlineClass =
      'border-primary-foreground/30 bg-transparent text-primary-foreground hover:bg-primary-foreground/10 hover:text-primary-foreground';

   const toggleClass = (active: boolean) =>
      cn(
         'rounded-full',
         !active && variant === 'hero' && heroOutlineClass,
         active && variant === 'hero' && 'bg-primary-foreground text-primary hover:bg-primary-foreground/90 hover:text-primary',
      );

   return (
      <div
         className={cn(
            'flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between',
            variant === 'hero' ? 'ssu-catalog-hero' : 'mb-6',
         )}
      >
         <div>
            <p className="ssu-kicker mb-1">{kicker}</p>
            <h1 className="font-display text-2xl font-bold capitalize sm:text-3xl">{title}</h1>
            {description ? <p className="mt-2 max-w-2xl text-sm opacity-80">{description}</p> : null}
         </div>

         <div className="flex shrink-0 gap-2">
            <TooltipProvider delayDuration={0}>
               <Tooltip>
                  <TooltipTrigger asChild>
                     <Button
                        size="icon"
                        className={toggleClass(viewType === 'grid')}
                        variant={viewType === 'grid' ? 'default' : 'outline'}
                        onClick={() => onViewChange('grid')}
                     >
                        <Grid className="h-4 w-4" />
                     </Button>
                  </TooltipTrigger>
                  <TooltipContent>
                     <p>{gridLabel}</p>
                  </TooltipContent>
               </Tooltip>
            </TooltipProvider>

            <TooltipProvider delayDuration={0}>
               <Tooltip>
                  <TooltipTrigger asChild>
                     <Button
                        size="icon"
                        className={toggleClass(viewType === 'list')}
                        variant={viewType === 'list' ? 'default' : 'outline'}
                        onClick={() => onViewChange('list')}
                     >
                        <List className="h-4 w-4" />
                     </Button>
                  </TooltipTrigger>
                  <TooltipContent>
                     <p>{listLabel}</p>
                  </TooltipContent>
               </Tooltip>
            </TooltipProvider>
         </div>
      </div>
   );
};

export default CourseCatalogToolbar;
