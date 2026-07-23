import { Card } from '@/components/ui/card';
import { getQueryParams } from '@/lib/route';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { router, usePage } from '@inertiajs/react';

interface RoleCounts {
   all: number;
   admin: number;
   internal_employee: number;
   external: number;
   trainer: number;
}

interface RoleFilterOption {
   value: string;
   label: string;
}

interface Props {
   roleCounts: RoleCounts;
   roleFilters: RoleFilterOption[];
   routeName: string;
}

const RoleStats = ({ roleCounts, roleFilters, routeName }: Props) => {
   const page = usePage<SharedData>();
   const urlParams = getQueryParams(page.url);
   const activeFilter = (urlParams['role_filter'] as string) ?? 'all';

   const handleSelect = (value: string) => {
      const params = { ...urlParams } as Record<string, string>;

      if (value === 'all') {
         delete params.role_filter;
      } else {
         params.role_filter = value;
      }

      router.get(route(routeName, params), {}, { preserveState: true, preserveScroll: true });
   };

   const cards = roleFilters.map((filter) => ({
      ...filter,
      count: roleCounts[filter.value as keyof RoleCounts] ?? roleCounts.all,
   }));

   return (
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
         {cards.map((card) => {
            const isActive = activeFilter === card.value;

            return (
               <button key={card.value} type="button" onClick={() => handleSelect(card.value)} className="text-left">
                  <Card
                     className={cn(
                        'border p-4 transition-colors hover:border-primary/40',
                        isActive ? 'border-primary bg-primary/5 shadow-sm' : 'border-border',
                     )}
                  >
                     <p className="text-muted-foreground text-sm">{card.label}</p>
                     <p className="mt-1 text-2xl font-semibold">{card.count}</p>
                  </Card>
               </button>
            );
         })}
      </div>
   );
};

export default RoleStats;
