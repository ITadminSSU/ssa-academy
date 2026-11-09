import SearchInput from '@/components/search-input';
import { cn } from '@/lib/utils';
import { LayoutGrid, List } from 'lucide-react';

interface CourseCatalogToolbarProps {
   title: string;
   description?: string | null;
   viewType: string;
   onViewChange: (view: 'grid' | 'list') => void;
   onSearch: (value: string) => void;
   gridLabel?: string;
   listLabel?: string;
}

const CourseCatalogToolbar = ({
   title,
   description,
   viewType,
   onViewChange,
   onSearch,
   gridLabel = 'Tile',
   listLabel = 'List',
}: CourseCatalogToolbarProps) => {
   const side = (active: boolean) =>
      cn(
         'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold',
         active ? 'bg-primary text-primary-foreground' : 'text-primary hover:bg-primary/5',
      );

   return (
      <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
         <div className="min-w-0">
            <h1 className="font-display text-primary text-2xl font-bold sm:text-3xl">{title}</h1>
            {description ? <p className="text-muted-foreground mt-1 max-w-2xl text-sm">{description}</p> : null}
         </div>

         <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
            <SearchInput onChangeValue={onSearch} className="w-full sm:w-[220px]" />
            <div
               className="border-primary/30 inline-flex shrink-0 self-start rounded-full border bg-[color:var(--brand-grey)] p-0.5"
               role="group"
               aria-label="Catalog view"
            >
               <button type="button" className={side(viewType !== 'list')} aria-pressed={viewType !== 'list'} onClick={() => onViewChange('grid')}>
                  <LayoutGrid className="h-3.5 w-3.5" />
                  {gridLabel}
               </button>
               <button type="button" className={side(viewType === 'list')} aria-pressed={viewType === 'list'} onClick={() => onViewChange('list')}>
                  <List className="h-3.5 w-3.5" />
                  {listLabel}
               </button>
            </div>
         </div>
      </div>
   );
};

export default CourseCatalogToolbar;
